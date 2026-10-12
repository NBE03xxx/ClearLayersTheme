<?php

namespace Drupal\clear_layers_canvas_taxonomy\Plugin\Block;

use Drupal\system\Plugin\Block\SystemMenuBlock;

/**
 * Gives new footer menu placements one visible level in Clear Layers.
 *
 * Explicit settings on existing placements still override these defaults.
 */
final class FooterMenuBlock extends SystemMenuBlock {

  public function defaultConfiguration() {
    $defaults = parent::defaultConfiguration();
    if (\Drupal::config('system.theme')->get('default') === 'clear_layers') {
      $defaults['depth'] = 1;
    }
    return $defaults;
  }

}
