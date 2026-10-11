<?php

/**
 * @file
 * Checks or applies the saved starter kit on a new, empty Drupal site.
 * Default plan never installs modules, saves config or creates content.
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}
$mode = $extra[0] ?? 'plan';
if (!in_array($mode, ['plan', 'apply'], TRUE)) {
  throw new InvalidArgumentException('Use plan or apply.');
}
$path = dirname(__DIR__) . '/starter-kit';
if (!is_file($path . '/manifest.yml') || !is_file($path . '/recipe/recipe.yml')) {
  $message = 'No local starter-kit export is available. Site-specific recipe/content and manifest.yml are intentionally excluded from the public theme repository. Supply a reviewed export before applying it to a new, empty site.';
  echo json_encode(['mode' => $mode, 'ready' => FALSE, 'blockers' => [$message]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
  if ($mode === 'plan') {
    return;
  }
  throw new RuntimeException($message);
}
$manifest = Yaml::decode(file_get_contents($path . '/manifest.yml'));
$blockers = [];
foreach ($manifest['files'] as $relative => $expected) {
  if (!is_file($path . '/' . $relative) || hash_file('sha256', $path . '/' . $relative) !== $expected) {
    $blockers[] = 'Package checksum mismatch: ' . $relative;
  }
}
if (version_compare(\Drupal::VERSION, '11.4.0', '<') || version_compare(\Drupal::VERSION, '12.0.0', '>=')) {
  $blockers[] = 'This kit targets Drupal 11.4 and later Drupal 11 releases.';
}
$moduleList = \Drupal::service('extension.list.module');
$available = $moduleList->getList();
foreach ($manifest['summary']['required_modules'] as $module) {
  if (!isset($available[$module])) {
    $blockers[] = 'Missing module code: ' . $module;
  }
}
if (isset($available['canvas']) && ($available['canvas']->info['version'] ?? '') !== $manifest['canvas_version']) {
  $blockers[] = 'Use the exported Canvas version (' . $manifest['canvas_version'] . ') before upgrading and revalidating this kit.';
}
// Never replace another site's content, menus, taxonomy or uploaded files.
// The source site also fails this guard, preventing accidental reapplication.
$manager = \Drupal::entityTypeManager();
foreach (['canvas_page', 'node', 'taxonomy_term', 'menu_link_content', 'media', 'file'] as $type) {
  if ($manager->hasDefinition($type)) {
    $count = (int) $manager->getStorage($type)->getQuery()->accessCheck(FALSE)->count()->execute();
    if ($count > 0) {
      $blockers[] = 'Existing ' . $type . ' content (' . $count . '): use a new, empty site.';
    }
  }
}
if ($manager->hasDefinition('page_variant') && \Drupal::moduleHandler()->moduleExists('canvas')) {
  $drafts = \Drupal::service(\Drupal\canvas\AutoSave\AutoSaveManager::class)->getAllAutoSaveList(FALSE, FALSE);
  if ($drafts) {
    $blockers[] = 'Existing Canvas drafts: use a new, empty site.';
  }
}
$recipe = NULL;
if (!$blockers) {
  try {
    $recipe = Recipe::createFromDirectory($path . '/recipe');
  }
  catch (Throwable $error) {
    $blockers[] = $error->getMessage();
  }
}
echo json_encode([
  'mode' => $mode,
  'ready' => !$blockers,
  'blockers' => $blockers,
  'changes_on_apply' => [
    'Install required modules and Clear Layers',
    'Import saved layout, component versions, Views, fields and theme defaults',
    'Create Home, About, 404, Link, Privacy policy, menus, terms and two images',
    'Set the default theme, /home front page, /404 error page and saved timezone',
    'Allow anonymous and authenticated visitors to view public content and media',
  ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
if ($mode === 'plan') {
  return;
}
if ($blockers) {
  throw new RuntimeException('Starter kit application refused; resolve the blockers on a new site.');
}
RecipeRunner::processRecipe($recipe);
foreach ($recipe->content->data as $uuid => $data) {
  if (!\Drupal::service('entity.repository')->loadEntityByUuid($data['_meta']['entity_type'], $uuid)) {
    throw new RuntimeException('Imported content is missing: ' . $uuid);
  }
}
echo "Starter kit applied. Rebuild caches and verify the destination site.\n";
