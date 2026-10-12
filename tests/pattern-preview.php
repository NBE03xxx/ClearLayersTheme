<?php

/**
 * @file
 * Exercises Canvas pattern previews and checks that pages retain their layout.
 * Run after preparing the shared patterns and the /home page.
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Pattern;
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
$results = [];
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(\Drupal\user\Entity\User::load(1));
try {
  foreach (['site_header' => 'site-header', 'site_footer' => 'site-footer'] as $id => $component) {
    $pattern = Pattern::load($id);
    $check($pattern !== NULL, 'Prepare the shared patterns before running this test.');
    foreach (['/canvas/api/v0/layout-pattern/', '/canvas/api/v0/layout/pattern/'] as $prefix) {
      $data = json_decode($request($prefix . $id), TRUE, flags: JSON_THROW_ON_ERROR);
      $html = $data['html'];
      $check($count($html, $component) === 1, $id . ': the edited section must appear once.');
      $check($count($html, $component === 'site-header' ? 'site-footer' : 'site-header') === 0, $id . ': unrelated page sections leaked into the pattern preview.');
      $check(substr_count($html, '<!-- canvas-region-start-content -->') === 1 && substr_count($html, '<!-- canvas-region-end-content -->') === 1, $id . ': Canvas preview boundaries changed.');
      preg_match_all('/<!-- canvas-start-([a-f0-9-]{36}) -->/', $html, $matches);
      $expected = array_column($pattern->getComponentTree()->getValue(), 'uuid');
      $actual = $matches[1];
      sort($expected);
      sort($actual);
      $check($expected === $actual, $id . ': preview component selection no longer matches the pattern model.');
      $check(isset($data['layout'], $data['model']), $id . ': the editor layout/model was lost.');
      $results[$prefix . $id] = 'standalone and selectable';
    }
  }
  $data = json_decode($request('/canvas/api/v0/layout/page_variant/site_layout'), TRUE, flags: JSON_THROW_ON_ERROR);
  $check($count($data['html'], 'site-header') === 1 && $count($data['html'], 'site-footer') === 1, 'The page template editor must retain its header and footer.');
  $results['page_template_preview'] = 'retained';
}
finally {
  $switcher->switchBack();
}
$switcher->switchTo(new AnonymousUserSession());
try {
  $html = $request('/home');
  $check($count($html, 'site-header') === 1 && $count($html, 'site-footer') === 1, 'The published Home page lost or duplicated its header/footer.');
  $results['published_home'] = 'retained';
}
finally {
  $switcher->switchBack();
}
$check($before === $snapshot(), 'The preview checks changed saved configuration or auto-saved drafts.');
print json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
