<?php

/**
 * @file
 * Checks Gin Login and the public Canvas layout without submitting forms.
 * Run with Drush php:script; requires Gin Login and the site_layout variant.
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Plugin\DisplayVariant\CanvasPageVariant;
use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AnonymousUserSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}
$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};
$snapshot = static function (): array {
  $storage = \Drupal::service('config.storage');
  $configs = [];
  foreach ($storage->listAll() as $name) {
    $configs[$name] = $storage->read($name);
  }
  return [$configs, \Drupal::keyValue(AutoSaveManager::AUTO_SAVE_STORE)->getAll()];
};
$before = $snapshot();
$check(\Drupal::moduleHandler()->moduleExists('gin_login'), 'Enable Gin Login before running this check.');
$check(\Drupal::config('system.theme')->get('default') === 'clear_layers', 'Clear Layers must be the default theme.');
$check(\Drupal::config('system.theme')->get('admin') === 'gin', 'Gin must be the admin theme.');
$check(\Drupal::config('canvas.settings')->get('default_page_variant') === 'site_layout', 'The site_layout variant must be the default.');
$themes = \Drupal::service('theme.initialization');
$themeManager = \Drupal::theme();
$originalTheme = $themeManager->getActiveTheme();
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(new AnonymousUserSession());
try {
  // Exercise the real subscriber chain, including Canvas's -100 selection.
  $route = \Drupal::service('router.route_provider')->getRouteByName('user.login');
  $match = new RouteMatch('user.login', $route);
  foreach (['clear_layers' => CanvasPageVariant::PLUGIN_ID, 'gin' => 'block_page'] as $theme => $expected) {
    $themeManager->setActiveTheme($themes->initTheme($theme));
    $event = new PageDisplayVariantSelectionEvent('simple_page', $match);
    \Drupal::service('event_dispatcher')->dispatch($event, RenderEvents::SELECT_PAGE_DISPLAY_VARIANT);
    $check($event->getPluginId() === $expected, $theme . ': unexpected page display variant.');
    if ($theme === 'gin') {
      $check($event->getPluginConfiguration() === [], 'Canvas configuration leaked into core block rendering.');
    }
    $check(in_array('theme', $event->getCacheContexts(), TRUE), 'The selection must vary by theme.');
  }

  $request = static function (string $path): string {
    // CLI subrequests share the theme manager. Renegotiate as a new request
    // would, rather than reusing Gin from the previous login request.
    \Drupal::theme()->resetActiveTheme();
    $response = \Drupal::service('http_kernel')->handle(
      Request::create(\Drupal::request()->getSchemeAndHttpHost() . $path),
      HttpKernelInterface::SUB_REQUEST,
    );
    if ($response->getStatusCode() !== 200) {
      throw new RuntimeException($path . ': HTTP ' . $response->getStatusCode());
    }
    return $response->getContent();
  };
  foreach (['/user/login' => 'user-login-form', '/user/password' => 'user-pass'] as $path => $formId) {
    $html = $request($path);
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($document);
    $check($xpath->query('//div[@class="user-form-page"]')->length === 1, $path . ': Gin Login page layout is missing.');
    $check($xpath->query('//main//form[@id="' . $formId . '"]')->length === 1, $path . ': main form is missing.');
    $check($xpath->query('//h1')->length === 1, $path . ': expected one page heading.');
    $check($xpath->query('//*[@id="main-content"]')->length === 1, $path . ': skip-link target is missing.');
    $check($xpath->query('//div[@class="user-form-page__wallpaper"]//img')->length === 1, $path . ': configured wallpaper is missing.');
    $check(!str_contains($html, 'data-component-id="clear_layers:'), $path . ': public Canvas components leaked into Gin.');
  }
  $html = $request('/');
  foreach (['site-header', 'site-footer'] as $component) {
    $check(substr_count($html, 'data-component-id="clear_layers:' . $component . '"') === 1, 'The public page lost or duplicated its ' . $component . '.');
  }
}
finally {
  $themeManager->setActiveTheme($originalTheme);
  $switcher->switchBack();
}
$check($before === $snapshot(), 'Checks changed saved configuration or Canvas drafts.');
echo "PASS: Gin login/password forms, heading, skip link and wallpaper; public Canvas header/footer; theme-aware variant selection; configuration and drafts unchanged.\n";
