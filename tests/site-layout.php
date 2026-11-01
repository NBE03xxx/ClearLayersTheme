<?php

/**
 * @file
 * Checks migrated layouts, editor selection and listing rendering read-only.
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\PageVariant;
use Drupal\clear_layers\Setup\SiteLayoutMigration;
use Drupal\Component\Utility\Html;
use Drupal\Core\Session\AnonymousUserSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}
$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};
$snapshot = static function (): array {
  $storage = \Drupal::service('config.storage');
  $configs = [];
  foreach ($storage->listAll() as $name) {
    $configs[$name] = $storage->read($name);
  }
  return [$configs, \Drupal::keyValue(AutoSaveManager::AUTO_SAVE_STORE)->getAll()];
};
$before = $snapshot();

// Preserve nested slots and saved inputs rather than flattening the section.
$pattern = [
  ['uuid' => 'header', 'component_id' => 'sdc.clear_layers.site-header', 'inputs' => ['branding_width' => 47]],
  ['uuid' => 'child', 'parent_uuid' => 'header', 'slot' => 'branding', 'component_id' => 'block.system_branding_block', 'inputs' => ['use_site_name' => FALSE]],
];
$reference = ['uuid' => 'reference', 'component_id' => 'sdc.clear_layers.shared-site-section', 'inputs' => ['section' => 'header']];
$check(SiteLayoutMigration::expandSections([$reference], ['header' => $pattern]) === $pattern, 'Migration lost section inputs, UUIDs or slots.');
$refused = FALSE;
try {
  SiteLayoutMigration::expandSections([$reference, $reference], ['header' => $pattern]);
}
catch (RuntimeException) {
  $refused = TRUE;
}
$check($refused, 'Repeated shared references must not create duplicate editor UUIDs.');
$taxonomyTree = [
  $reference,
  ['uuid' => 'panel', 'component_id' => 'sdc.clear_layers.taxonomy-listing', 'inputs' => ['title_size' => 32]],
  ['uuid' => 'marker', 'component_id' => 'marker.page_content', 'parent_uuid' => 'panel', 'slot' => 'content', 'inputs' => []],
  array_replace($reference, ['uuid' => 'footer-reference', 'inputs' => ['section' => 'footer']]),
];
SiteLayoutMigration::assertStandardTaxonomyTree($taxonomyTree);
$taxonomyTree[1]['inputs']['title_size'] = 48;
$refused = FALSE;
try {
  SiteLayoutMigration::assertStandardTaxonomyTree($taxonomyTree);
}
catch (RuntimeException) {
  $refused = TRUE;
}
$check($refused, 'Custom taxonomy presentation must not be silently discarded.');
$plan = SiteLayoutMigration::plan();
$check(!$plan['updates'] && !$plan['delete'] && !$plan['default_changed'], 'Finish the migration before running this check; a second migration must be a no-op.');

$request = static function (string $path): string {
  $response = \Drupal::service('http_kernel')->handle(
    Request::create(\Drupal::request()->getSchemeAndHttpHost() . $path),
    HttpKernelInterface::SUB_REQUEST,
  );
  if ($response->getStatusCode() !== 200) {
    throw new RuntimeException($path . ': HTTP ' . $response->getStatusCode());
  }
  return $response->getContent();
};
$count = static fn (string $html, string $component): int => substr_count($html, 'data-component-id="clear_layers:' . $component . '"');
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(\Drupal\user\Entity\User::load(1));
try {
  $data = json_decode($request('/canvas/api/v0/layout/page_variant/site_layout'), TRUE, flags: JSON_THROW_ON_ERROR);
  preg_match_all('/<!-- canvas-start-([a-f0-9-]{36}) -->/', $data['html'], $matches);
  $expected = array_column(PageVariant::load('site_layout')->getComponentTree()->getValue(), 'uuid');
  $actual = $matches[1];
  sort($expected);
  sort($actual);
  $check($expected === $actual, 'Header/footer children must all have selectable Canvas annotations in the page template editor.');
  $check($count($data['html'], 'site-header') === 1 && $count($data['html'], 'site-footer') === 1, 'The editor duplicated a shared section.');
}
finally {
  $switcher->switchBack();
}

$paths = [\Drupal::config('system.site')->get('page.front')];
$manager = \Drupal::entityTypeManager();
foreach (['blog', 'page'] as $bundle) {
  $ids = $manager->getStorage('node')->getQuery()->accessCheck(FALSE)->condition('type', $bundle)->condition('status', 1)->range(0, 1)->execute();
  foreach ($ids as $id) {
    $paths[] = '/node/' . $id;
  }
}
$search = \Drupal\views\Views::getView('search');
if ($search) {
  foreach ($search->storage->get('display') as $display) {
    if ($display['display_plugin'] === 'page' && !str_contains($display['display_options']['path'] ?? '%', '%')) {
      $paths[] = '/' . $display['display_options']['path'];
    }
  }
}
$termIds = $manager->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE)->condition('status', 1)->range(0, 1)->execute();
foreach ($termIds as $id) {
  $paths[] = '/taxonomy/term/' . $id;
}
$results = [];
$switcher->switchTo(new AnonymousUserSession());
try {
  foreach (array_unique($paths) as $path) {
    $html = $request($path);
    $check($count($html, 'site-header') === 1 && $count($html, 'site-footer') === 1, $path . ': expected exactly one header and footer.');
    $check(!str_contains($html, 'data-site-section='), $path . ': a legacy shared-pattern reference remains.');
    $check(strpos($html, '<header') < strpos($html, '<footer'), $path . ': shared sections are out of order.');
    if (str_starts_with($path, '/taxonomy/term/')) {
      $check(substr_count($html, 'class="c-taxonomy-listing"') === 1, 'The taxonomy view must own exactly one listing panel.');
      $check(str_contains($html, 'view-taxonomy-term'), 'The taxonomy view results were lost.');
      preg_match('/<h1[^>]*class="c-taxonomy-listing__title"[^>]*>(.*?)<\/h1>/s', $html, $heading);
      $term = $manager->getStorage('taxonomy_term')->load(basename($path));
      $check(trim(Html::decodeEntities(strip_tags($heading[1] ?? ''))) === $term->label(), 'The listing heading must retain the current term name.');
    }
    $results[$path] = 'HTTP 200; one header/footer';
  }
  // Feeds bypass the HTML page layout and must not acquire the listing panel.
  $feedDisplay = \Drupal\views\Views::getView('taxonomy_term')?->storage->get('display')['feed_1'] ?? NULL;
  if ($feedDisplay && ($feedDisplay['display_options']['path'] ?? '') === 'taxonomy/term/%/feed') {
    foreach ($termIds as $id) {
      $feed = $request('/taxonomy/term/' . $id . '/feed');
      $check(str_contains($feed, '<rss') && !str_contains($feed, 'c-taxonomy-listing') && !str_contains($feed, 'data-component-id="clear_layers:site-header"'), 'The RSS feed acquired HTML layout markup.');
      $results['taxonomy_rss'] = 'RSS retained; no HTML layout';
    }
  }
}
finally {
  $switcher->switchBack();
}
$check($before === $snapshot(), 'Read-only checks changed configuration or Canvas drafts.');
print json_encode(['migration' => 'idempotent; nested inputs retained; duplicate UUIDs refused', 'editor' => 'all components selectable', 'pages' => $results, 'saved_configuration' => 'unchanged'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
