<?php

/**
 * @file
 * Resolves theme background images and previously saved media selections.
 */

use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Lists raster images stored directly in the theme's background directory.
 */
function clear_layers_background_images(): array {
  $directory = realpath(__DIR__ . '/../background');
  if (!$directory || !is_dir($directory)) {
    return [];
  }
  $labels = [
    'blue-glowing-waves' => t('青（Blue）'),
    'green-glowing-waves' => t('緑（Green）'),
    'red-glowing-waves' => t('赤（Red）'),
    'amber-glowing-waves' => t('琥珀色（Amber）'),
    'monochrome-glowing-waves' => t('モノクロ（Monochrome）'),
  ];
  $path = \Drupal::service('extension.list.theme')->getPath('clear_layers') . '/background/';
  $images = [];
  foreach (new \DirectoryIterator($directory) as $file) {
    $resolved = $file->getRealPath();
    if (!$file->isFile() || !$resolved || !str_starts_with($resolved, $directory . DIRECTORY_SEPARATOR)) {
      continue;
    }
    $extension = strtolower($file->getExtension());
    if (!in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'avif', 'gif'], TRUE) || !@getimagesize($resolved)) {
      continue;
    }
    $filename = $file->getFilename();
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    $images[$filename] = [
      'uri' => $path . $filename,
      'label' => $labels[$stem] ?? ucfirst(str_replace(['-', '_'], ' ', $stem)),
    ];
  }
  ksort($images, SORT_NATURAL | SORT_FLAG_CASE);
  return $images;
}

/**
 * Gets the default bundled background, with the original asset as a fallback.
 */
function clear_layers_background_default_uri(): string {
  return clear_layers_background_images()['blue-glowing-waves.png']['uri']
    ?? \Drupal::service('extension.list.theme')->getPath('clear_layers') . '/images/backgrounds/waves-blue.png';
}

/**
 * Resolves a selected filename strictly through the available image list.
 */
function clear_layers_background_image_uri(mixed $filename): ?string {
  if (!is_string($filename) || $filename === '') {
    return NULL;
  }
  return clear_layers_background_images()[$filename]['uri'] ?? NULL;
}

/**
 * Loads the configured media by UUID, so configuration survives ID changes.
 */
function clear_layers_background_media(?string $uuid): ?MediaInterface {
  if (!$uuid || !\Drupal::moduleHandler()->moduleExists('media')) {
    return NULL;
  }
  $items = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['uuid' => $uuid]);
  return $items ? reset($items) : NULL;
}

/**
 * Gets a public image file suitable for a site-wide decorative background.
 */
function clear_layers_background_file(MediaInterface $media): ?FileInterface {
  if (!$media->isPublished() || $media->getSource()->getPluginId() !== 'image') {
    return NULL;
  }
  $field_name = $media->getSource()->getConfiguration()['source_field'];
  $file = $media->get($field_name)->entity;
  if (!$file instanceof FileInterface || StreamWrapperManager::getScheme($file->getFileUri()) !== 'public') {
    return NULL;
  }
  return is_file($file->getFileUri()) ? $file : NULL;
}

/**
 * Quotes a file URL as a CSS string, independently of HTML escaping.
 */
function clear_layers_background_css_url(string $url): string {
  return 'url("' . str_replace(
    ['\\', '"', "\n", "\r", "\f"],
    ['\\\\', '\\"', '\\a ', '\\d ', '\\c '],
    $url,
  ) . '")';
}
