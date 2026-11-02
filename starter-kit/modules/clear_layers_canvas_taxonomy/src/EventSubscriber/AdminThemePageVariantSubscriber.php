<?php

declare(strict_types=1);

namespace Drupal\clear_layers_canvas_taxonomy\EventSubscriber;

use Drupal\canvas\Plugin\DisplayVariant\CanvasPageVariant;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps the site's Canvas layout out of pages rendered by the admin theme.
 */
final class AdminThemePageVariantSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  public function onSelectPageDisplayVariant(PageDisplayVariantSelectionEvent $event): void {
    if ($event->getPluginId() !== CanvasPageVariant::PLUGIN_ID) {
      return;
    }

    $config = $this->configFactory->get('system.theme');
    $event->addCacheableDependency($config);
    $event->addCacheContexts(['route', 'theme']);
    if ($config->get('default') !== 'clear_layers'
      || $config->get('admin') === $config->get('default')
      || $this->themeManager->getActiveTheme()->getName() !== $config->get('admin')) {
      return;
    }

    // Gin Login selects the admin theme for routes without _admin_route.
    // Canvas 1.12 only checks that route flag, so the site's default variant
    // replaces Gin Login's page template with unstyled front-end components.
    // Restore core page rendering so the selected theme owns the page layout.
    $event->setPluginId($this->moduleHandler->moduleExists('block') ? 'block_page' : 'simple_page');
    $event->setPluginConfiguration([]);
  }

  public static function getSubscribedEvents(): array {
    // Canvas selects its page variant at -100; adjust only its selection.
    return [RenderEvents::SELECT_PAGE_DISPLAY_VARIANT => ['onSelectPageDisplayVariant', -200]];
  }

}
