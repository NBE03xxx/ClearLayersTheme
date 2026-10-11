<?php

namespace Drupal\clear_layers\Render;

use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Applies a footer menu's title to its block before rendering the SDC slot.
 */
final class FooterMenu implements TrustedCallbackInterface {

  /**
   * Registers a slot alteration only for this theme's footer menu component.
   */
  public static function prepare(array $element): array {
    if (($element['#component'] ?? '') === 'clear_layers:footer-menu') {
      $element['#slotsAlter'][] = [self::class, 'overrideTitle'];
    }
    return $element;
  }

  /**
   * Overrides the label without changing saved block configuration or links.
   */
  public static function overrideTitle(array $slot, array $props): array {
    $title = trim($props['title_override'] ?? '');
    self::alterBlockLabels($slot, $title);
    return $slot;
  }

  /**
   * Finds menu blocks inside this wrapper, including nested layout containers.
   */
  private static function alterBlockLabels(array &$element, string $title): void {
    if (($element['#base_plugin_id'] ?? '') === 'system_menu_block') {
      $element['#clear_layers_footer_menu'] = TRUE;
      // The existing block template uses this label for both its heading and
      // accessible navigation name. Keep the existing show/hide CSS control.
      if ($title !== '') {
        $element['#configuration']['label'] = $title;
      }
    }
    foreach (Element::children($element) as $key) {
      self::alterBlockLabels($element[$key], $title);
    }
    // Canvas wraps each block/component in an exception-safe container.
    if (($element['#type'] ?? '') === 'component_container'
      && is_array($element['#component'] ?? NULL)) {
      self::alterBlockLabels($element['#component'], $title);
    }
    // Layouts may themselves be SDCs with nested render arrays in their slots.
    foreach ($element['#slots'] ?? [] as $key => $slot) {
      if (is_array($slot)) {
        self::alterBlockLabels($element['#slots'][$key], $title);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['prepare', 'overrideTitle'];
  }

}
