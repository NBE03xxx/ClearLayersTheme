<?php

/**
 * @file
 * One-time Canvas/theme migration using Drupal APIs, with a read-only plan mode.
 *
 * Run with Drush php:script and `-- plan` (default) or `-- apply`.
 * Install clear_layers first. Take a database backup before applying.
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Folder;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\ContentEntityTypeInterface;

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}

$mode = $extra[0] ?? 'plan';
if (!in_array($mode, ['plan', 'apply'], TRUE)) {
  throw new InvalidArgumentException('Use plan or apply.');
}
$apply = $mode === 'apply';
$oldTheme = 'liquidglass_theme';
$newTheme = 'clear_layers';
$etm = \Drupal::entityTypeManager();
$factory = \Drupal::configFactory();
if (!array_key_exists($oldTheme, $factory->get('core.extension')->get('theme') ?? [])) {
  throw new RuntimeException('The source theme must be installed before migration.');
}
if ($apply && $factory->get('system.theme')->get('default') === $newTheme) {
  throw new RuntimeException('The target theme is already the default; do not reapply this migration.');
}
if (\Drupal::service(AutoSaveManager::class)->getAllAutoSaveList(FALSE, FALSE)) {
  throw new RuntimeException('Canvas has unsaved changes. Publish or resolve them before migration.');
}
if ($apply && !array_key_exists($newTheme, $factory->get('core.extension')->get('theme') ?? [])) {
  throw new RuntimeException('Install clear_layers before applying the migration.');
}

$rewrite = static function (mixed $value) use (&$rewrite, $oldTheme, $newTheme): mixed {
  if (is_array($value)) {
    $result = [];
    foreach ($value as $key => $item) {
      $newKey = is_string($key) ? str_replace($oldTheme, $newTheme, $key) : $key;
      $result[$newKey] = $rewrite($item);
    }
    return $result;
  }
  return is_string($value) ? str_replace([$oldTheme, 'images/liquid-glass/'], [$newTheme, 'images/backgrounds/'], $value) : $value;
};
$map = [];
$components = [];
foreach ($etm->getStorage('component')->loadMultiple() as $component) {
  if ($component->get('provider') === $oldTheme) {
    if ($component->get('source') !== 'sdc') {
      throw new RuntimeException('Unexpected non-SDC theme component: ' . $component->id());
    }
    $map[$component->id()] = str_replace($oldTheme, $newTheme, $component->id());
    $components[$component->id()] = $component;
  }
}
$rewriteTree = static function (array $tree) use ($map): array {
  foreach ($tree as &$item) {
    if (isset($map[$item['component_id'] ?? ''])) {
      $item['component_id'] = $map[$item['component_id']];
    }
  }
  return $tree;
};

$content = [];
foreach ($etm->getDefinitions() as $type => $definition) {
  if (!$definition instanceof ContentEntityTypeInterface) {
    continue;
  }
  $fields = array_filter(\Drupal::service('entity_field.manager')->getFieldStorageDefinitions($type), static fn ($field): bool => $field->getType() === 'component_tree');
  if (!$fields) {
    continue;
  }
  $storage = $etm->getStorage($type);
  $current = $storage->getQuery()->accessCheck(FALSE)->execute();
  $all = $storage->getQuery()->accessCheck(FALSE)->allRevisions()->execute();
  // Historical revisions without old component references need no rewrite.
  // Stop only when a non-current revision actually needs migration.
  foreach ($all as $revisionId => $entityId) {
    $revision = $storage->loadRevision($revisionId);
    if ((string) $storage->load($entityId)->getRevisionId() === (string) $revisionId) {
      continue;
    }
    foreach ($revision->getTranslationLanguages() as $langcode => $language) {
      foreach ($fields as $field => $fieldDefinition) {
        $tree = $revision->getTranslation($langcode)->get($field)->getValue();
        if ($tree !== $rewriteTree($tree)) {
          throw new RuntimeException("$type has historical or forward component references; extend the migration before applying.");
        }
      }
    }
  }
  foreach ($storage->loadMultiple($current) as $entity) {
    $changes = [];
    foreach ($entity->getTranslationLanguages() as $langcode => $language) {
      $translation = $entity->getTranslation($langcode);
      foreach ($fields as $field => $fieldDefinition) {
        $before = $translation->get($field)->getValue();
        $after = $rewriteTree($before);
        if ($before !== $after) {
          $changes[$langcode][$field] = $after;
        }
      }
    }
    if ($changes) {
      $content[] = [$entity, $changes];
    }
  }
}

$configTrees = [];
foreach (['content_template', 'page_variant', 'pattern'] as $type) {
  foreach ($etm->getStorage($type)->loadMultiple() as $entity) {
    $before = $entity->get('component_tree') ?? [];
    $after = $rewriteTree($before);
    if ($before !== $after) {
      $configTrees[] = [$entity, $after];
    }
  }
}
$regions = $etm->getStorage('page_region')->loadByProperties(['theme' => $oldTheme]);
$blocks = $etm->getStorage('block')->loadByProperties(['theme' => $oldTheme]);
$foldersBefore = [];
foreach (Folder::loadMultiple() as $folder) {
  $foldersBefore[$folder->id()] = $folder->get('items');
}
$report = ['target_installed' => array_key_exists($newTheme, $factory->get('core.extension')->get('theme') ?? []), 'mode' => $apply ? 'apply' : 'plan', 'components' => count($components), 'content_entities' => count($content), 'config_templates' => count($configTrees), 'regions' => count($regions), 'blocks' => count($blocks), 'component_map' => $map];
if (!$apply) {
  echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
  return;
}

$transaction = Database::getConnection()->startTransaction();
try {
  // Preserve all existing component versions and input schemas, including
  // manually hidden Library items; source IDs are deliberately not versioned.
  $storage = $etm->getStorage('component');
  foreach ($components as $oldId => $original) {
    $data = $rewrite($original->toArray());
    $newId = $map[$oldId];
    $existing = $storage->load($newId);
    $data['uuid'] = $existing?->uuid() ?? \Drupal::service('uuid')->generate();
    unset($data['_core']);
    // Importing the source's complete history deliberately replaces newly
    // discovered defaults. Canvas requires synchronization when switching
    // active_version and versioned_properties together on an existing entity.
    $existing?->setSyncing(TRUE);
    $replacement = $existing ? $storage->updateFromStorageRecord($existing, $data) : $storage->create($data);
    $replacement->save();
    if ($replacement->getActiveVersion() !== $original->getActiveVersion() || $replacement->getSettings() !== $original->getSettings() || $replacement->status() !== $original->status()) {
      throw new RuntimeException('Component version/settings/status changed: ' . $oldId);
    }
    foreach ($replacement->getVersions() as $version) {
      $replacement->loadVersion($version);
      $original->loadVersion($version);
      // Historical version hashes can predate Canvas's current hash algorithm.
      // Preserve those stored IDs and compare old/new runtime interpretation.
      $computed = $replacement->getComponentSource()->generateVersionHash();
      if ($computed !== $original->getComponentSource()->generateVersionHash() || ($version === $replacement->getActiveVersion() && $computed !== $version) || $replacement->getSettings() !== $original->getSettings()) {
        throw new RuntimeException('Migrated component version differs: ' . $newId . ':' . $version);
      }
    }
    $replacement->resetToActiveVersion();
    $original->resetToActiveVersion();
  }

  $settings = $factory->get($oldTheme . '.settings')->getRawData();
  unset($settings['_core']);
  $factory->getEditable($newTheme . '.settings')->setData($rewrite($settings))->save();
  foreach ($blocks as $original) {
    $data = $rewrite($original->toArray());
    $storage = $etm->getStorage('block');
    $existing = $storage->load($data['id']);
    $data['uuid'] = $existing?->uuid() ?? \Drupal::service('uuid')->generate();
    unset($data['_core']);
    $replacement = $existing ? $storage->updateFromStorageRecord($existing, $data) : $storage->create($data);
    $replacement->save();
  }
  foreach ($regions as $original) {
    $data = $rewrite($original->toArray());
    $data['component_tree'] = $rewriteTree($original->get('component_tree') ?? []);
    $storage = $etm->getStorage('page_region');
    $existing = $storage->load($data['id']);
    $data['uuid'] = $existing?->uuid() ?? \Drupal::service('uuid')->generate();
    unset($data['_core']);
    $replacement = $existing ? $storage->updateFromStorageRecord($existing, $data) : $storage->create($data);
    $replacement->save();
  }

  foreach ($configTrees as [$entity, $tree]) {
    if (in_array($entity->label(), ['LiquidGlass', 'LiquidGlass Theme'], TRUE)) {
      $entity->set('label', 'Clear Layers');
    }
    $entity->set('component_tree', $tree)->save();
  }
  foreach ($content as [$entity, $changes]) {
    $before = $entity->toArray();
    $revision = $entity->getRevisionId();
    foreach ($changes as $langcode => $fields) {
      foreach ($fields as $field => $tree) {
        $entity->getTranslation($langcode)->set($field, $tree);
      }
    }
    // Synchronization prevents ChangedItem from treating the provider-ID
    // rewrite as an editorial update and replacing the original timestamp.
    $entity->setSyncing(TRUE);
    $entity->setNewRevision(FALSE);
    $entity->save();
    if ($entity->getRevisionId() !== $revision) {
      throw new RuntimeException('Unexpected new content revision.');
    }
    // Compare reloaded storage values, rather than in-memory booleans against
    // database strings (e.g. revision_default TRUE versus stored "1").
    $storage = $etm->getStorage($entity->getEntityTypeId());
    $storage->resetCache([$entity->id()]);
    $after = $storage->loadRevision($revision)->toArray();
    foreach (array_keys(reset($changes)) as $field) {
      unset($before[$field], $after[$field]);
    }
    if ($before !== $after) {
      $changedFields = array_keys(array_filter($after, static fn ($value, $key): bool => !array_key_exists($key, $before) || $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));
      throw new RuntimeException('Unrelated content fields changed: ' . $entity->getEntityTypeId() . ':' . $entity->id() . ': ' . implode(', ', $changedFields));
    }
  }

  // Replace the folder membership after Component::postSave() auto-categorizes
  // new items. The old IDs remain installed until all usages have migrated.
  foreach (Folder::loadMultiple() as $folder) {
    if (isset($foldersBefore[$folder->id()])) {
      // Discovery may already have placed both providers in the same folder.
      // Mapping them to one provider must keep each Library item only once.
      $items = array_values(array_unique(array_map(static fn (string $id): string => $map[$id] ?? $id, $foldersBefore[$folder->id()])));
      if ($items !== $folder->get('items')) {
        $folder->set('items', $items)->save();
      }
    }
    elseif (array_diff($folder->get('items'), array_values($map)) === []) {
      // Drop auto-created groups for internal/hidden components, retaining
      // the exact set of folders that was explicitly curated on this site.
      $folder->delete();
    }
    else {
      throw new RuntimeException('Unexpected new folder contents.');
    }
  }
  $reasons = \Drupal::service(\Drupal\canvas\ComponentIncompatibilityReasonRepository::class);
  foreach ($reasons->getReasons()['sdc'] ?? [] as $id => $messages) {
    if (str_starts_with($id, 'sdc.' . $oldTheme . '.')) {
      $reasons->storeReasons('sdc', str_replace($oldTheme, $newTheme, $id), $messages);
    }
  }
  // Refuse to finish if any content/config/autosave still uses old components.
  $audit = \Drupal::service(\Drupal\canvas\Audit\ComponentAudit::class);
  \Drupal::service('cache_tags.invalidator')->invalidateTags(['config:canvas.component_list', 'canvas_component_tree']);
  foreach ($components as $original) {
    if ($audit->hasUsages($original, \Drupal\canvas\Audit\RevisionAuditEnum::All) || $audit->hasUsages($original, \Drupal\canvas\Audit\RevisionAuditEnum::AutoSave)) {
      throw new RuntimeException('Old component still has usages: ' . $original->id());
    }
  }
  // Switch only after every Canvas reference has migrated and passed audit.
  $factory->getEditable('system.theme')->set('default', $newTheme)->save();
  unset($transaction);
}
catch (Throwable $error) {
  $transaction->rollBack();
  throw $error;
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
