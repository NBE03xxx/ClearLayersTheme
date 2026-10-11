<?php

namespace Drupal\clear_layers_canvas_taxonomy\EventSubscriber;

use Drupal\canvas\Entity\PageVariant;
use Drupal\canvas\Plugin\DisplayVariant\CanvasPageVariant;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Selects a common Canvas layout around the existing taxonomy listing.
 */
final class TaxonomyPageVariantSubscriber implements EventSubscriberInterface {

  public const VARIANT_ID = 'taxonomy_listing';

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public function onSelect(PageDisplayVariantSelectionEvent $event): void {
    if ($event->getRouteMatch()->getRouteName() !== 'entity.taxonomy_term.canonical') {
      return;
    }
    // Missing or disabled templates fall back to Canvas' normal selection.
    // Invalidate the selection even if the template does not exist yet.
    $event->addCacheTags(['config:canvas.page_variant.' . self::VARIANT_ID]);
    $variant = $this->entityTypeManager->getStorage('page_variant')->load(self::VARIANT_ID);
    if (!$variant instanceof PageVariant || !$variant->status()) {
      return;
    }
    $event->addCacheableDependency($variant);
    $event->addCacheContexts(['route']);
    $event->setPluginId(CanvasPageVariant::PLUGIN_ID);
    $event->setPluginConfiguration([
      CanvasPageVariant::PREVIEW_KEY => FALSE,
      CanvasPageVariant::VARIANT_ID_KEY => $variant->id(),
    ]);
  }

  public static function getSubscribedEvents(): array {
    // Run after Canvas' default selector (-100); only term pages are changed.
    return [RenderEvents::SELECT_PAGE_DISPLAY_VARIANT => ['onSelect', -110]];
  }

}
