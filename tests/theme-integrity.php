<?php

/**
 * @file
 * Loads every Clear Layers SDC and Drupal template without saving site data.
 * Run with Drush php:script after installing the theme.
 */

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}

$manager = \Drupal::service('plugin.manager.sdc');
$twig = \Drupal::service('twig');
$components = 0;
foreach ($manager->getDefinitions() as $id => $definition) {
  if (!str_starts_with($id, 'clear_layers:')) {
    continue;
  }
  $manager->createInstance($id);
  $twig->load($id);
  $components++;
}
if ($components === 0) {
  throw new RuntimeException('Install Clear Layers before running this check.');
}

$directory = dirname(__DIR__);
$templates = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory . '/templates'));
foreach ($iterator as $file) {
  if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
    continue;
  }
  $twig->load(substr($file->getPathname(), strlen(DRUPAL_ROOT) + 1));
  $templates++;
}
echo "PASS: $components SDC definitions/templates and $templates Drupal Twig templates.\n";
