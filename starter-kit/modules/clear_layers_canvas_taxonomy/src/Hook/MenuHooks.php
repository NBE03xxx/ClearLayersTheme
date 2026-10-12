<?php

namespace Drupal\clear_layers_canvas_taxonomy\Hook;

use Drupal\clear_layers_canvas_taxonomy\Plugin\Block\FooterMenuBlock;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\system\Plugin\Block\SystemMenuBlock;

/**
 * Supplies Canvas-safe defaults for the standard footer menu block.
 */
final class MenuHooks {

  #[Hook('block_alter')]
  public function blockAlter(array &$definitions): void {
    // Change only the core footer derivative. Preserve another module's custom
    // implementation, and keep the main/header menu definition untouched.
    if (($definitions['system_menu_block:footer']['class'] ?? NULL) === SystemMenuBlock::class) {
      $definitions['system_menu_block:footer']['class'] = FooterMenuBlock::class;
    }
  }

}
