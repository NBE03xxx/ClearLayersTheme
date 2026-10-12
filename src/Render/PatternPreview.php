<?php

namespace Drupal\clear_layers\Render;

use Drupal\canvas\Entity\Pattern;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Keeps the Canvas pattern preview independent of the site's page template.
 */
final class PatternPreview {

  public static function prepare(array $variables): array {
    $route = \Drupal::routeMatch();
    if (!$route->getRouteObject()?->getOption('_canvas_use_template_draft')) {
      return $variables;
    }
    foreach ($route->getParameters() as $parameter) {
      if ($parameter instanceof Pattern) {
        $content = $variables['content'] ?? [];
        if (is_array($content) && ($preview = self::findMainContent($content)) !== NULL) {
          // HtmlRenderer has already rendered the editor's main content before
          // injecting it into the Page content marker. Reuse that exact result
          // to retain draft inputs, translations and Canvas selection comments.
          // CanvasPreviewRenderer currently strips ordinary page regions, but
          // cannot strip a PageVariant tree held in #content (Canvas 1.12).
          BubbleableMetadata::createFromRenderArray($content)
            ->merge(BubbleableMetadata::createFromRenderArray($preview))
            ->addCacheContexts(['route'])
            ->applyTo($preview);
          $variables['content'] = $preview;
        }
        break;
      }
    }
    return $variables;
  }

  /**
   * Finds the complete, rendered content region by Canvas's preview boundary.
   */
  private static function findMainContent(array $element): ?array {
    if (isset($element['#markup'])
      && str_starts_with((string) $element['#markup'], '<!-- canvas-region-start-content -->')
      && str_ends_with((string) $element['#markup'], '<!-- canvas-region-end-content -->')) {
      // Discard comments belonging to the outer page's marker, while retaining
      // the pattern's own component comments inside its rendered markup.
      $preview = ['#markup' => $element['#markup']];
      BubbleableMetadata::createFromRenderArray($element)->applyTo($preview);
      return $preview;
    }
    foreach ($element as $key => $child) {
      if (is_array($child) && !in_array($key, ['#attached', '#cache'], TRUE)) {
        if (($preview = self::findMainContent($child)) !== NULL) {
          return $preview;
        }
      }
    }
    return NULL;
  }

}
