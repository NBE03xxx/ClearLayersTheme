<?php

namespace Drupal\clear_layers\Render;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\system\Entity\Menu;

/**
 * Builds the standard account menu without a Canvas block configuration form.
 */
final class AccountMenu implements TrustedCallbackInterface {

  public static function prepare(array $element): array {
    if (($element['#component'] ?? '') !== 'clear_layers:account-menu') {
      return $element;
    }
    $props = $element['#props'] ?? [];
    $menu = Menu::load('account');
    $title = trim($props['title_override'] ?? '');
    // Twig Tweak retains Core access checks, menu cache metadata and the
    // dynamic login/logout link, including Drupal's logout CSRF token.
    $build = \Drupal::service('twig_tweak.block_view_builder')->build('system_menu_block:account', [
      'label' => $title !== '' ? $title : ($menu?->label() ?? (string) t('User account menu')),
      'label_display' => TRUE,
      'level' => 1,
      'depth' => 1,
      'expand_all_items' => TRUE,
      'ignore_active_trail' => TRUE,
    ]);
    $build['#clear_layers_footer_menu'] = TRUE;
    // This wrapper changes the block output; do not reuse the unmodified
    // Twig Tweak block cache or another placement's account-link visibility.
    $build['#cache']['keys'][] = 'clear_layers_account_menu';
    $build['#cache']['keys'][] = (int) !empty($props['show_account_link']);
    if (isset($build['content']['#items'])) {
      // This component renders login/logout and optionally My account. Keep
      // all cache metadata from the full, access-checked tree, including that
      // of removed links. Do not change the site's account menu configuration.
      $links = ['user.logout' => TRUE];
      if (!empty($props['show_account_link'])) {
        $links['user.page'] = TRUE;
      }
      $build['content']['#items'] = array_intersect_key($build['content']['#items'], $links);
    }
    $element['#slots']['account_menu'] = $build;
    return $element;
  }

  public static function trustedCallbacks(): array {
    return ['prepare'];
  }

}
