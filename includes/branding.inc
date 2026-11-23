<?php

/**
 * @file
 * Shared defaults and bounds for site branding layout settings.
 */

/**
 * Returns a valid alignment, or NULL to retain the existing layout.
 */
function clear_layers_branding_alignment(mixed $value): ?string {
  return in_array($value, ['left', 'center', 'right'], TRUE) ? $value : NULL;
}

/**
 * Returns the branding width as a percentage of its parent.
 */
function clear_layers_branding_width(mixed $value): int {
  $width = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
  return $width === FALSE ? 100 : $width;
}
