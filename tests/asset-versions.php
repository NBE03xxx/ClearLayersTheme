<?php

/**
 * @file
 * Checks theme and SDC asset versions without saving site configuration.
 * Run with Drush php:script after rebuilding caches.
 */

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}

require_once dirname(__DIR__) . '/clear_layers.theme';
$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};
$directory = dirname(__DIR__);
$temporary_directory = $directory . '/tmp';
$created_directory = !is_dir($temporary_directory);
if ($created_directory) {
  mkdir($temporary_directory, 0700);
}
$fixture = tempnam($temporary_directory, 'asset-version-');
$relative = substr($fixture, strlen($directory) + 1);
$root_relative = substr($fixture, strlen(DRUPAL_ROOT));
$before = \Drupal::service('config.storage')->read('clear_layers.settings');
$theme = \Drupal::theme()->getActiveTheme();
\Drupal::theme()->setActiveTheme(\Drupal::service('theme.initialization')->initTheme('clear_layers'));
try {
  $version = static function (string $file, string $type = 'css'): string {
    $library = ['version' => '5.x'];
    $library += $type === 'css' ? ['css' => ['theme' => [$file => []]]] : ['js' => [$file => []]];
    $libraries = ['fixture' => $library];
    clear_layers_library_info_alter($libraries, 'clear_layers');
    return $libraries['fixture']['version'];
  };

  file_put_contents($fixture, '.sample { color: blue; }');
  $blue = $version($relative);
  $check(preg_match('/^[a-f0-9]{64}$/', $blue) === 1, 'Local assets did not receive a content version.');
  $check($version($relative) === $blue, 'The same content produced an unstable version.');
  touch($fixture, time() - 3600);
  clearstatcache(TRUE, $fixture);
  $check($version($relative) === $blue, 'Changing only the timestamp changed the version.');
  $timestamp = filemtime($fixture);
  file_put_contents($fixture, '.sample { color: red; }');
  touch($fixture, $timestamp);
  clearstatcache(TRUE, $fixture);
  $check($version($relative) !== $blue, 'A CSS change with the same timestamp retained a stale version.');
  $script_before = $version($relative, 'js');
  file_put_contents($fixture, 'window.sample = true;');
  $check($version($relative, 'js') !== $script_before, 'A JavaScript change retained a stale version.');

  $libraries = [
    'components.clear_layers--fixture' => ['css' => ['component' => [$root_relative => []]]],
    'components.clear_layers--relative' => ['css' => ['component' => ['..' . $root_relative => []]]],
    'components.other_theme--fixture' => ['version' => 'keep', 'css' => ['component' => [$root_relative => []]]],
  ];
  clear_layers_library_info_alter($libraries, 'core');
  $check(isset($libraries['components.clear_layers--fixture']['version']), 'Root-relative SDC assets did not receive a version.');
  $check(isset($libraries['components.clear_layers--relative']['version']), 'Core-relative SDC assets did not receive a version.');
  $check($libraries['components.other_theme--fixture']['version'] === 'keep', 'Another theme was modified.');

  $external = ['cdn' => ['version' => '4.1.4', 'css' => ['theme' => ['https://cdn.example.com/library.css' => ['type' => 'external']]]]];
  $libraries = $external;
  clear_layers_library_info_alter($libraries, 'clear_layers');
  $check($libraries === $external, 'An external library version was changed.');
  $other = ['fixture' => ['version' => 'keep', 'css' => ['theme' => [$root_relative => []]]]];
  $libraries = $other;
  clear_layers_library_info_alter($libraries, 'another_extension');
  $check($libraries === $other, 'Another extension was modified.');

  $discovery = \Drupal::service('library.discovery');
  foreach (['clear_layers/global', 'core/components.clear_layers--site-header', 'core/components.clear_layers--site-branding'] as $id) {
    [$extension, $name] = explode('/', $id, 2);
    $library = $discovery->getLibraryByName($extension, $name);
    $check(preg_match('/^[a-f0-9]{64}$/', $library['version'] ?? '') === 1, $id . ': discovered library has no content version.');
  }
}
finally {
  unlink($fixture);
  if ($created_directory) {
    rmdir($temporary_directory);
  }
  \Drupal::theme()->setActiveTheme($theme);
}
$check(\Drupal::service('config.storage')->read('clear_layers.settings') === $before, 'The test changed saved theme settings.');
echo "PASS: Stable content versions, CSS/JS changes, unchanged timestamps, SDC assets, external/other libraries retained and actual library discovery. Saved settings unchanged.\n";
