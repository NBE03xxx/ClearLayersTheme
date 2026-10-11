<?php

namespace Drupal\clear_layers\Render;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Resolves a taxonomy listing's heading from the current public route.
 */
final class TaxonomyListing implements TrustedCallbackInterface {

  public static function prepare(array $element): array {
    if (($element['#component'] ?? '') !== 'clear_layers:taxonomy-listing') {
      return $element;
    }
    $route = \Drupal::routeMatch();
    $cacheability = CacheableMetadata::createFromRenderArray($element)->addCacheContexts(['route', 'languages:language_content']);
    if ($route->getRouteName() === 'entity.taxonomy_term.canonical'
      && ($term = $route->getParameter('taxonomy_term')) instanceof TermInterface) {
      $term = \Drupal::service('entity.repository')->getTranslationFromContext($term);
      $access = $term->access('view', NULL, TRUE);
      $cacheability->addCacheableDependency($term)->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $element['#props']['taxonomy_title'] = $term->label();
      }
    }
    // Canvas template previews have no term: Twig shows a generic heading.
    $cacheability->applyTo($element);
    return $element;
  }

  public static function trustedCallbacks(): array {
    return ['prepare'];
  }

}
