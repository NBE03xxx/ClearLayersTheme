<?php

namespace Drupal\clear_layers\Render;

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Pattern;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Renders a live reference to a Canvas pattern instead of copying its tree.
 */
final class SharedSiteSection implements TrustedCallbackInterface {

  public static function prepare(array $element): array {
    if (($element['#component'] ?? '') !== 'clear_layers:shared-site-section') {
      return $element;
    }
    $section = $element['#props']['section'] ?? 'header';
    if (!in_array($section, ['header', 'footer'], TRUE)) {
      return $element;
    }
    $id = 'site_' . $section;
    $preview = (bool) ($element['#props']['canvas_is_preview'] ?? FALSE);
    $cacheability = CacheableMetadata::createFromRenderArray($element)
      ->addCacheTags(['config:canvas.pattern.' . $id])
      ->addCacheContexts(['route.name.is_canvas_editor_ui', 'languages:language_interface']);
    $pattern = Pattern::load($id);
    if ($pattern && $pattern->status()) {
      $cacheability->addCacheableDependency($pattern);
      if ($preview) {
        $draft = \Drupal::service(AutoSaveManager::class)->getAutoSaveEntity($pattern);
        $cacheability->addCacheableDependency($draft);
        if ($draft->entity instanceof Pattern && self::isSafe($draft->entity)) {
          $violations = \Drupal::service('config.typed')
            ->createFromNameAndData($draft->entity->getConfigDependencyName(), $draft->entity->toArray())
            ->validate();
          if (count($violations) === 0) {
            $pattern = $draft->entity;
          }
        }
      }
      if (self::isSafe($pattern)) {
        // The containing page's layout model only owns this reference, not
        // the pattern's child UUIDs. Do not emit nested Canvas selection/slot
        // annotations here: they would point to components outside its model.
        // Draft selection above still applies in preview. The pattern's own
        // editor renders its tree normally and keeps all editing annotations.
        $element['#slots']['shared_content'] = $pattern->getComponentTree()->toRenderable($pattern, FALSE);
      }
    }
    if (!isset($element['#slots']['shared_content']) && $preview) {
      $element['#slots']['shared_content'] = ['#plain_text' => '共通パターン ' . $id . ' を確認してください。共通部品自身はパターン内へ配置できません。'];
    }
    $cacheability->applyTo($element);
    return $element;
  }

  /**
   * Prevents a shared pattern from recursively referring to itself or its peer.
   */
  private static function isSafe(Pattern $pattern): bool {
    foreach ($pattern->get('component_tree') ?? [] as $row) {
      if ($row['component_id'] === 'sdc.clear_layers.shared-site-section') {
        return FALSE;
      }
    }
    return TRUE;
  }

  public static function trustedCallbacks(): array {
    return ['prepare'];
  }

}
