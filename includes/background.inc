<?php

/**
 * @file
 * Resolves the site's background from an existing image media item.
 */

use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

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
