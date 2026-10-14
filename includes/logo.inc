<?php

/**
 * @file
 * Resolves the five bundled logos without overriding custom uploaded logos.
 */

use Drupal\Core\Extension\ThemeSettingsProvider;

/**
 * Lists the bundled logo colors and their corresponding backgrounds.
 */
function clear_layers_logo_colors(): array {
  return [
    'blue' => ['label' => t('青'), 'background' => 'blue-glowing-waves.png'],
    'green' => ['label' => t('緑'), 'background' => 'green-glowing-waves.png'],
    'red' => ['label' => t('赤'), 'background' => 'red-glowing-waves.png'],
    'amber' => ['label' => t('琥珀色'), 'background' => 'amber-glowing-waves.png'],
    'monochrome' => ['label' => t('モノクロ'), 'background' => 'monochrome-glowing-waves.png'],
  ];
}

/**
 * Defaults missing or obsolete settings to automatic selection.
 */
function clear_layers_logo_selection(mixed $selection): string {
  return is_string($selection) && isset(clear_layers_logo_colors()[$selection]) ? $selection : 'auto';
}

/**
 * Selects a color; unrecognized or missing backgrounds use blue.
 */
function clear_layers_logo_color(mixed $selection, mixed $background): string {
  $selection = clear_layers_logo_selection($selection);
  if ($selection !== 'auto') {
    return $selection;
  }
  if (clear_layers_background_image_uri($background)) {
    foreach (clear_layers_logo_colors() as $color => $definition) {
      if ($background === $definition['background']) {
        return $color;
      }
    }
  }
  return 'blue';
}

/**
 * Gets an allowlisted logo path relative to the Drupal root.
 */
function clear_layers_logo_uri(string $color): string {
  $color = clear_layers_logo_selection($color);
  if ($color === 'auto') {
    $color = 'blue';
  }
  return \Drupal::service('extension.list.theme')->getPath('clear_layers') . '/images/branding/logo-' . $color . '.svg';
}

/**
 * Returns the selected default logo URL, or NULL when using a custom logo.
 */
function clear_layers_default_logo_url(): ?string {
  $provider = \Drupal::service(ThemeSettingsProvider::class);
  if (!$provider->getSetting('logo.use_default', 'clear_layers')) {
    return NULL;
  }
  $config = \Drupal::config('clear_layers.settings');
  $color = clear_layers_logo_color($config->get('logo_color'), $config->get('background_image'));
  return \Drupal::service('file_url_generator')->generateString(clear_layers_logo_uri($color));
}
