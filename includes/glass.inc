<?php

/**
 * @file
 * Shared defaults and bounds for Clear Layers settings.
 */

/**
 * Defines the site-wide glass controls.
 */
function clear_layers_glass_settings(): array {
  return [
    'glass_transparency' => ['default' => 30, 'max' => 100],
    'glass_blur' => ['default' => 12, 'max' => 40],
  ];
}

/**
 * Returns a bounded integer setting, retaining an explicit zero.
 */
function clear_layers_glass_value(string $key, mixed $value): int {
  $definition = clear_layers_glass_settings()[$key];
  $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $definition['max']]]);
  return $validated === FALSE ? $definition['default'] : $validated;
}

/**
 * Defines the site-wide glass elevation presets.
 */
function clear_layers_glass_depths(): array {
  return [
    'none' => 'なし',
    'subtle' => '控えめ',
    'standard' => '標準',
    'strong' => '強め',
  ];
}

/**
 * Returns a valid glass elevation preset.
 */
function clear_layers_glass_depth(mixed $value): string {
  return is_string($value) && array_key_exists($value, clear_layers_glass_depths()) ? $value : 'standard';
}
