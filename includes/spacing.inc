<?php

/**
 * @file
 * Shared defaults and bounds for page spacing settings.
 */

/**
 * Returns a usable spacing value, retaining an explicit zero.
 */
function clear_layers_spacing_value(mixed $value): int {
  $spacing = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 64]]);
  return $spacing === FALSE ? 24 : $spacing;
}
