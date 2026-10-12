<?php

/**
 * @file
 * Saves published Clear Layers defaults without changing the Drupal database.
 * drush php:script .../scripts/export-starter-kit.php -- plan|export
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\DefaultContent\Exporter;
use Drupal\Core\DefaultContent\PreExportEvent;
use Drupal\Core\Entity\ContentEntityInterface;

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}
$mode = $extra[0] ?? 'plan';
if (!in_array($mode, ['plan', 'export'], TRUE)) {
  throw new InvalidArgumentException('Use plan or export.');
}
$themePath = dirname(__DIR__);
$destination = $themePath . '/starter-kit';
$storage = \Drupal::service('config.storage');
$before = [];
foreach ($storage->listAll() as $name) {
  $before[$name] = $storage->read($name);
}
$drafts = \Drupal::service(AutoSaveManager::class)->getAllAutoSaveList(FALSE, FALSE);
$manager = \Drupal::entityTypeManager();
$configs = [];
$entities = [];
$queue = [
  'canvas.pattern.site_header', 'canvas.pattern.site_footer',
  'canvas.page_variant.jisaba', 'canvas.page_variant.site_layout',
  'canvas.page_variant.taxonomy_listing',
  'canvas.content_template.node.blog.full', 'canvas.content_template.node.blog.card',
  'canvas.content_template.node.page.full',
  'views.view.taxonomy_term',
];
$queue = array_merge($queue, $storage->listAll('canvas.component.sdc.clear_layers.'), $storage->listAll('block.block.clear_layers_'));
$exporter = \Drupal::service(Exporter::class);
$dispatcher = \Drupal::service('event_dispatcher');
// Accounts and author/revision history are not starter content. Use the
// destination site's administrator when Core imports these public entities.
$omitHistory = static function (PreExportEvent $event): void {
  foreach (['uid', 'revision_uid', 'revision_user', 'revision_log', 'revision_log_message', 'revision_translation_affected', 'content_translation_uid'] as $field) {
    $event->setExportable($field, FALSE);
  }
  if ($event->entity->getEntityTypeId() === 'taxonomy_term') {
    $reference = $event->getCallbacks()['field_item:entity_reference'];
    $event->setCallback('parent', static fn ($item, $metadata): ?array => (int) $item->target_id === 0 ? ['target_id' => 0] : $reference($item, $metadata));
  }
};
$dispatcher->addListener(PreExportEvent::class, $omitHistory, -100);
$entityQueue = [];
foreach ([3, 7, 8] as $id) {
  $entityQueue[] = $manager->getStorage('canvas_page')->load($id);
}
foreach ([1, 16] as $id) {
  $entityQueue[] = $manager->getStorage('node')->load($id);
}
foreach ($manager->getStorage('menu_link_content')->loadMultiple() as $link) {
  if (in_array($link->getMenuName(), ['main', 'footer'], TRUE) && $link->isEnabled()) {
    $entityQueue[] = $link;
  }
}
// Category aliases used by the navigation and the footer's tag list need
// actual terms; articles themselves are deliberately excluded.
$entityQueue = array_merge($entityQueue, array_values(array_filter($manager->getStorage('taxonomy_term')->loadMultiple(), static fn ($term): bool => $term->isPublished())));
$settings = $storage->read('clear_layers.settings');
if (!empty($settings['background_media_uuid'])) {
  $entityQueue[] = \Drupal::service('entity.repository')->loadEntityByUuid('media', $settings['background_media_uuid']);
}
// Include the article editing form, not just fields currently drawn by Views.
foreach (['node.blog', 'node.page'] as $bundleKey) {
  $queue = array_merge($queue, $storage->listAll('field.field.' . $bundleKey . '.'), $storage->listAll('core.entity_form_display.' . $bundleKey . '.'), $storage->listAll('core.entity_view_display.' . $bundleKey . '.'));
}
try {
  while ($queue || $entityQueue) {
    while ($entityQueue) {
      $entity = array_shift($entityQueue);
      if (!$entity instanceof ContentEntityInterface) {
        throw new RuntimeException('An expected starter entity is missing.');
      }
      $key = $entity->getEntityTypeId() . ':' . $entity->uuid();
      if (isset($entities[$key])) {
        continue;
      }
      if ($entity->getEntityTypeId() === 'user' || ($entity->getEntityTypeId() === 'node' && $entity->bundle() !== 'page')) {
        throw new RuntimeException('Starter content cannot include accounts or blog articles: ' . $key);
      }
      if (method_exists($entity, 'isPublished') && !$entity->isPublished()) {
        throw new RuntimeException('Starter content must be published: ' . $key);
      }
      if ($entity->getEntityTypeId() === 'file' && !str_starts_with($entity->getFileUri(), 'public://')) {
        throw new RuntimeException('Only public files may be packaged: ' . $key);
      }
      $result = $exporter->export(clone $entity);
      $entities[$key] = ['entity' => $entity, 'result' => $result];
      foreach ($result->metadata->getDependencies() as [$type, $uuid]) {
        $entityQueue[] = \Drupal::service('entity.repository')->loadEntityByUuid($type, $uuid);
      }
      $type = $entity->getEntityTypeId();
      $bundle = $entity->bundle();
      $queue = array_merge($queue, $storage->listAll('field.field.' . $type . '.' . $bundle . '.'), $storage->listAll('core.entity_form_display.' . $type . '.' . $bundle . '.'), $storage->listAll('core.entity_view_display.' . $type . '.' . $bundle . '.'));
      $bundleType = $entity->getEntityType()->getBundleEntityType();
      if ($bundleType) {
        $bundleEntity = $manager->getStorage($bundleType)->load($bundle);
        $queue[] = $bundleEntity->getConfigDependencyName();
      }
      if ($entity->hasField('components')) {
        foreach ($entity->get('components') as $item) {
          $queue[] = 'canvas.component.' . $item->component_id;
        }
      }
    }
    while ($queue) {
      $name = array_shift($queue);
      if (isset($configs[$name])) {
        continue;
      }
      $data = $storage->read($name);
      if ($data === FALSE) {
        throw new RuntimeException('Missing config dependency: ' . $name);
      }
      unset($data['uuid'], $data['_core']);
      $configs[$name] = $data;
      $queue = array_merge($queue, $data['dependencies']['config'] ?? []);
      foreach ($data['dependencies']['content'] ?? [] as $dependency) {
        [$type, , $uuid] = explode(':', $dependency, 3);
        $entityQueue[] = \Drupal::service('entity.repository')->loadEntityByUuid($type, $uuid);
      }
    }
  }
}
finally {
  $dispatcher->removeListener(PreExportEvent::class, $omitHistory);
}

$modules = ['canvas', 'clear_layers_canvas_taxonomy', 'clear_layers_starter_kit', 'menu_link_content', 'path', 'twig_tweak', 'twig_field_value'];
foreach ($configs as $data) {
  $modules = array_merge($modules, $data['dependencies']['module'] ?? []);
}
$modules = array_values(array_unique($modules));
sort($modules);
ksort($configs);
$types = [];
foreach ($entities as ['entity' => $entity]) {
  $type = $entity->getEntityTypeId();
  $types[$type] = ($types[$type] ?? 0) + 1;
}
$summary = ['configuration_files' => count($configs), 'content' => $types, 'required_modules' => $modules, 'pending_drafts_excluded' => count($drafts), 'destination' => $destination];
if ($mode === 'export') {
  if (is_dir($destination . '/recipe')) {
    throw new RuntimeException('Starter kit already exists. Archive it before exporting a replacement.');
  }
  $writeYaml = static function (string $path, array $data): void {
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, TRUE)) {
      throw new RuntimeException('Cannot create directory for ' . $path);
    }
    if (file_put_contents($path, Yaml::encode($data)) === FALSE) {
      throw new RuntimeException('Cannot write ' . $path);
    }
  };
  $actions = [
    'clear_layers.settings' => ['simpleConfigUpdate' => $settings],
    'system.theme' => ['simpleConfigUpdate' => ['default' => 'clear_layers']],
    'system.site' => ['simpleConfigUpdate' => ['page.front' => '/home', 'page.404' => '/404']],
    'canvas.settings' => ['simpleConfigUpdate' => ['default_page_variant' => 'jisaba']],
    'system.date' => ['simpleConfigUpdate' => ['timezone.default' => \Drupal::config('system.date')->get('timezone.default')]],
    'user.role.anonymous' => ['grantPermissions' => ['access content', 'view media']],
    'user.role.authenticated' => ['grantPermissions' => ['access content', 'view media']],
  ];
  // Canvas discovers component configs before recipe import. Explicit actions
  // restore all stored versions (including old article-card compatibility).
  foreach ($configs as $name => $data) {
    if (str_starts_with($name, 'canvas.component.')) {
      $actions[$name] = ['clear_layersRestoreComponent' => $data];
    }
    $writeYaml($destination . '/recipe/config/' . $name . '.yml', $data);
  }
  $strict = array_values(array_filter(array_keys($configs), static fn (string $name): bool => str_starts_with($name, 'canvas.pattern.') || str_starts_with($name, 'canvas.page_variant.') || str_starts_with($name, 'canvas.content_template.') || str_starts_with($name, 'views.view.')));
  $writeYaml($destination . '/recipe/recipe.yml', [
    'name' => 'Clear Layers starter kit',
    'description' => 'Published Clear Layers layouts, starter pages and current theme defaults.',
    'type' => 'Clear Layers starter kit',
    'install' => [...$modules, 'clear_layers'],
    'config' => ['strict' => $strict, 'actions' => $actions],
  ]);
  foreach ($entities as ['entity' => $entity, 'result' => $result]) {
    $entityPath = $destination . '/recipe/content/' . $entity->getEntityTypeId();
    if (!is_dir($entityPath)) {
      mkdir($entityPath, 0775, TRUE);
    }
    file_put_contents($entityPath . '/' . $entity->uuid() . '.yml', (string) $result);
    foreach ($result->metadata->getAttachments() as $source => $name) {
      // Core's importer looks beside the YAML for basename(uri), which can
      // differ from the human-readable filename (e.g. percent-encoded names).
      if ($entity->getEntityTypeId() === 'file') {
        $name = basename($result->data['default']['uri'][0]['value']);
      }
      if (basename($name) !== $name || !is_file($source) || !copy($source, $entityPath . '/' . $name)) {
        throw new RuntimeException('Cannot copy public file attachment.');
      }
    }
  }
  // Ship the maintained helper, including safe defaults for Canvas menu forms.
  $moduleSource = $themePath . '/starter-kit/modules/clear_layers_canvas_taxonomy';
  foreach (['clear_layers_canvas_taxonomy.info.yml', 'clear_layers_canvas_taxonomy.services.yml', 'README.md', 'src/EventSubscriber/TaxonomyPageVariantSubscriber.php', 'src/Hook/MenuHooks.php', 'src/Plugin/Block/FooterMenuBlock.php'] as $relative) {
    if (!is_file($moduleSource . '/' . $relative)) {
      throw new RuntimeException('Missing maintained Canvas helper file: ' . $relative);
    }
    $target = $destination . '/modules/clear_layers_canvas_taxonomy/' . $relative;
    if (!is_dir(dirname($target))) {
      mkdir(dirname($target), 0775, TRUE);
    }
    if (realpath($moduleSource . '/' . $relative) !== realpath($target)
      && !copy($moduleSource . '/' . $relative, $target)) {
      throw new RuntimeException('Cannot copy taxonomy helper.');
    }
  }
  // The installation-only action module is maintained in this theme so the
  // running site needs no new modules. Restore it from the sibling source.
  $actionSource = $themePath . '/starter-kit-module';
  foreach (['clear_layers_starter_kit.info.yml', 'src/Plugin/ConfigAction/RestoreComponent.php'] as $relative) {
    $target = $destination . '/modules/clear_layers_starter_kit/' . $relative;
    if (!is_dir(dirname($target))) {
      mkdir(dirname($target), 0775, TRUE);
    }
    if (!copy($actionSource . '/' . $relative, $target)) {
      throw new RuntimeException('Cannot copy component restore helper.');
    }
  }
  $writeYaml($themePath . '/config/install/clear_layers.settings.yml', $settings);
  $projects = [];
  $extensions = \Drupal::service('extension.list.module')->getList();
  $lock = json_decode(file_get_contents(dirname(DRUPAL_ROOT) . '/composer.lock'), TRUE, flags: JSON_THROW_ON_ERROR);
  foreach ($lock['packages'] as $package) {
    foreach ($modules as $module) {
      if (!isset($extensions[$module]) || str_starts_with($extensions[$module]->getPath(), 'core/')) {
        continue;
      }
      $project = $extensions[$module]->info['project'] ?? explode('/', $extensions[$module]->getPath())[2] ?? $module;
      if ($package['name'] === 'drupal/' . $project) {
        $projects[$package['name']] = $package['version'];
      }
    }
  }
  ksort($projects);
  $files = [];
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destination, FilesystemIterator::SKIP_DOTS));
  foreach ($iterator as $file) {
    if ($file->isFile() && $file->getFilename() !== 'manifest.yml') {
      $files[substr($file->getPathname(), strlen($destination) + 1)] = hash_file('sha256', $file->getPathname());
    }
  }
  ksort($files);
  $writeYaml($destination . '/manifest.yml', [
    'format_version' => 1,
    'exported_at' => gmdate(DATE_ATOM),
    'drupal_version' => \Drupal::VERSION,
    'canvas_version' => \Drupal::service('extension.list.module')->getExtensionInfo('canvas')['version'],
    'theme_settings' => $settings,
    'summary' => $summary,
    'composer_packages' => $projects,
    'files' => $files,
  ]);
}
foreach ($before as $name => $data) {
  if ($storage->read($name) !== $data) {
    throw new RuntimeException('Export changed active config: ' . $name);
  }
}
if ($storage->listAll() !== array_keys($before) || \Drupal::service(AutoSaveManager::class)->getAllAutoSaveList(FALSE, FALSE) !== $drafts) {
  throw new RuntimeException('Export changed config names or drafts.');
}
echo json_encode($summary + ['mode' => $mode, 'active_site_unchanged' => TRUE], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
