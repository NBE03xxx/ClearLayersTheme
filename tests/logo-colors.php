<?php

/**
 * @file
 * Checks logo selection through Drupal and Canvas without saving settings.
 */

use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Pattern;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\system\Form\ThemeSettingsForm;
use Drupal\user\Entity\User;

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}

require_once dirname(__DIR__) . '/theme-settings.php';
require_once dirname(__DIR__) . '/clear_layers.theme';
$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};
$before = \Drupal::service('config.storage')->read('clear_layers.settings');
$overrides = $GLOBALS['config']['clear_layers.settings'] ?? NULL;
$theme = \Drupal::theme()->getActiveTheme();
\Drupal::theme()->setActiveTheme(\Drupal::service('theme.initialization')->initTheme('clear_layers'));
$override = static function (array $values): void {
  $GLOBALS['config']['clear_layers.settings'] = $values;
  \Drupal::configFactory()->reset('clear_layers.settings');
  \Drupal::service('cache.memory')->delete('theme_settings:clear_layers');
};
$renderer = \Drupal::service('renderer');
$render = static function (array $build) use ($renderer): array {
  $build['#cache']['max-age'] = 0;
  $context = new RenderContext();
  $html = $renderer->executeInRenderContext($context, static function () use ($renderer, &$build) {
    return (string) $renderer->render($build);
  });
  $metadata = $context->isEmpty() ? new BubbleableMetadata() : $context->pop();
  return [$html, $metadata->getCacheTags()];
};
$block = static function (bool $show = TRUE): array {
  $plugin = \Drupal::service('plugin.manager.block')->createInstance('system_branding_block', [
    'label' => 'Branding test', 'label_display' => FALSE,
    'use_site_logo' => $show, 'use_site_name' => FALSE, 'use_site_slogan' => FALSE,
  ]);
  return [
    '#theme' => 'block', '#attributes' => [], '#id' => 'clear_layers_logo_test',
    '#configuration' => $plugin->getConfiguration(),
    '#plugin_id' => $plugin->getPluginId(), '#base_plugin_id' => $plugin->getBaseId(),
    '#derivative_plugin_id' => $plugin->getDerivativeId(), 'content' => $plugin->build(),
  ];
};
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
try {
  $colors = clear_layers_logo_colors();
  $check(count($colors) === 5, 'Expected five logo colors.');
  foreach ($colors as $color => $definition) {
    $document = new DOMDocument();
    $check($document->load(DRUPAL_ROOT . '/' . clear_layers_logo_uri($color)), 'Logo SVG is invalid.');
    $check($document->documentElement->getAttribute('viewBox') === '0 0 512 512', 'Logo geometry changed.');
    $check($document->documentElement->getAttribute('width') === '512', 'Logo intrinsic width is missing.');
    $override(['logo' => ['use_default' => TRUE], 'background_image' => $definition['background']]);
    [$html, $tags] = $render($block());
    $check(str_contains($html, 'logo-' . $color . '.svg'), 'Automatic logo did not follow ' . $definition['background']);
    $check(in_array('config:clear_layers.settings', $tags, TRUE), 'Rendered logo did not bubble its settings dependency.');
    $check(in_array('config:system.theme.global', $tags, TRUE), 'Global logo settings dependency is missing.');
    foreach ($colors as $manual => $unused) {
      $override(['logo' => ['use_default' => TRUE], 'background_image' => $definition['background'], 'logo_color' => $manual]);
      [$html] = $render($block());
      $check(str_contains($html, 'logo-' . $manual . '.svg'), 'Manual logo selection was ignored.');
    }
  }

  foreach (['', 'missing.png', '../background/red-glowing-waves.png', '__legacy_media__', NULL, []] as $background) {
    $check(clear_layers_logo_color('auto', $background) === 'blue', 'Unknown background did not fall back to blue.');
  }
  foreach ([NULL, '', '../logo.svg', []] as $selection) {
    $check(clear_layers_logo_color($selection, 'red-glowing-waves.png') === 'red', 'Missing/invalid color did not fall back to automatic selection.');
  }

  $override(['logo' => ['use_default' => FALSE, 'path' => 'themes/custom/clear_layers/images/branding/jisaba-life-logo-v2.png'], 'logo_color' => 'red']);
  [$html] = $render($block());
  $check(str_contains($html, 'jisaba-life-logo-v2.png') && !str_contains($html, 'logo-red.svg'), 'Custom logo was replaced.');
  $override(['logo' => ['use_default' => TRUE], 'background_image' => 'red-glowing-waves.png']);
  [$html] = $render($block(FALSE));
  $check(!str_contains($html, 'site-branding__logo'), 'A hidden logo became visible.');

  $state = new FormState();
  $form = \Drupal::formBuilder()->buildForm(ThemeSettingsForm::class, $state->addBuildInfo('args', ['clear_layers']));
  $check($form['logo']['logo_color']['#value'] === 'auto', 'Existing sites without a color setting did not default to automatic selection.');
  $check(array_keys($form['logo']['logo_color']['#options']) === array_merge(['auto'], array_keys($colors)), 'Color choices missing from theme settings.');
  [$html] = $render($form);
  $check(substr_count($html, 'clear-layers-logo-sample--dark') === 5, 'Light/dark logo previews are missing.');
  $check(str_contains($html, 'name="logo_color"'), 'Color setting would not be submitted at the expected key.');
  $typed = \Drupal::service('config.typed')->createFromNameAndData('clear_layers.settings', $before + ['logo_color' => 'auto']);
  $check(count($typed->get('logo_color')->validate()) === 0, 'Logo setting schema rejected automatic selection.');

  if (\Drupal::moduleHandler()->moduleExists('canvas')) {
    $component = Component::load('block.system_branding_block');
    $check($component !== NULL && $component->status(), 'Enable the Canvas branding block before running this test.');
    foreach ($colors as $color => $definition) {
      $override(['logo' => ['use_default' => TRUE], 'background_image' => $definition['background']]);
      $pattern = Pattern::create([
        'id' => 'clear_layers_logo_test', 'label' => 'Logo test',
        'component_tree' => [[
          'uuid' => \Drupal::service('uuid')->generate(),
          'component_id' => $component->id(), 'component_version' => $component->getActiveVersion(),
          'inputs' => ['label' => 'Logo test', 'label_display' => '0', 'use_site_logo' => TRUE, 'use_site_name' => FALSE, 'use_site_slogan' => FALSE],
        ]],
      ]);
      foreach ([FALSE, TRUE] as $preview) {
        [$html, $tags] = $render($pattern->getComponentTree()->toRenderable($pattern, $preview));
        $check(str_contains($html, 'logo-' . $color . '.svg'), 'Canvas did not use the selected logo.');
        $check(in_array('config:clear_layers.settings', $tags, TRUE), 'Canvas logo settings dependency is missing.');
      }
    }
  }
}
finally {
  if ($overrides === NULL) {
    unset($GLOBALS['config']['clear_layers.settings']);
  }
  else {
    $GLOBALS['config']['clear_layers.settings'] = $overrides;
  }
  \Drupal::configFactory()->reset('clear_layers.settings');
  \Drupal::service('cache.memory')->delete('theme_settings:clear_layers');
  \Drupal::theme()->setActiveTheme($theme);
  $switcher->switchBack();
}
$check(\Drupal::service('config.storage')->read('clear_layers.settings') === $before, 'The test changed saved theme settings.');
echo "PASS: Five SVGs, automatic selection, 25 manual/background combinations, custom/hidden logos, form previews, schema, Drupal/Canvas rendering and cache dependencies. Saved settings unchanged.\n";
