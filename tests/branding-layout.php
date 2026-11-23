<?php

/**
 * @file
 * Checks branding layout settings without saving site configuration.
 * Run with Drush php:script after rebuilding caches.
 */

use Drupal\Core\Form\FormState;
use Drupal\Core\Template\Attribute;
use Drupal\system\Form\ThemeSettingsForm;

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
$attributes = static function (): array {
  $variables = ['html_attributes' => new Attribute()];
  clear_layers_preprocess_html($variables);
  return [$variables['html_attributes']->toArray(), $variables['clear_layers_meta']];
};

try {
  $override(['branding_alignment' => NULL, 'branding_width' => NULL]);
  [$html] = $attributes();
  $check(!isset($html['data-site-branding-alignment']), 'Existing Canvas alignment was overridden before saving the new settings.');
  $check(!str_contains($html['style'], '--site-branding-width:'), 'Existing widths changed before saving the new settings.');

  foreach (['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'] as $alignment => $justify) {
    foreach ([1, 50, 100] as $width) {
      foreach (['right', 'below'] as $position) {
        $override(['branding_alignment' => $alignment, 'branding_width' => $width, 'branding_text_position' => $position]);
        [$html, $meta] = $attributes();
        $check($html['data-site-branding-alignment'] === $alignment, 'The requested branding alignment was lost.');
        $check($html['data-branding-text-position'] === $position, 'Logo/text placement changed.');
        $check(str_contains($html['style'], '--site-branding-width: ' . $width . '%'), 'The percentage width was lost.');
        $check(str_contains($html['style'], '--site-branding-align: ' . $justify), 'The flex alignment is incorrect.');
        $check(in_array('config:clear_layers.settings', $meta['#cache']['tags'], TRUE), 'Saved layout changes would not invalidate cached HTML.');

        $state = new FormState();
        $form = \Drupal::formBuilder()->buildForm(ThemeSettingsForm::class, $state->addBuildInfo('args', ['clear_layers']));
        $fields = $form['clear_layers_settings'];
        $check($fields['branding_alignment']['#value'] === $alignment, 'The form did not load the selected alignment.');
        $check((int) $fields['branding_width']['#value'] === $width, 'The form did not load the selected width.');
        $check($fields['branding_width']['#parents'] === ['branding_width'], 'Width would be saved under the wrong configuration key.');
        $check(count(array_filter($form['#submit'], static fn ($handler) => $handler === 'clear_layers_branding_submit')) === 1, 'The layout submit handler is missing or duplicated.');
      }
    }
  }

  foreach ([0, 101, -1, 2.5, '', NULL, [], '50%; color: red'] as $invalid) {
    $check(clear_layers_branding_width($invalid) === 100, 'Invalid width was allowed into inline CSS.');
  }
  foreach (['start', 'center; color: red', NULL, []] as $invalid) {
    $check(clear_layers_branding_alignment($invalid) === NULL, 'Invalid alignment was allowed into inline CSS.');
  }
  $state = (new FormState())->setValues(['branding_alignment' => 'right', 'branding_width' => '50']);
  $form = [];
  clear_layers_branding_submit($form, $state);
  $check($state->getValue('branding_width') === 50, 'The submitted width was not normalized to an integer.');
  $check($state->getValue('branding_alignment') === 'right', 'The submitted alignment changed.');

  // The wrapper must fill a flex-based Canvas slot, so the SDC's percentage
  // resolves against that slot rather than a shrink-to-fit block.
  $plugin = \Drupal::service('plugin.manager.block')->createInstance('system_branding_block', [
    'label' => 'Branding layout test', 'label_display' => FALSE,
    'use_site_logo' => TRUE, 'use_site_name' => TRUE, 'use_site_slogan' => TRUE,
  ]);
  $build = [
    '#theme' => 'block', '#attributes' => [], '#id' => 'clear_layers_branding_layout_test',
    '#configuration' => $plugin->getConfiguration(),
    '#plugin_id' => $plugin->getPluginId(), '#base_plugin_id' => $plugin->getBaseId(),
    '#derivative_plugin_id' => $plugin->getDerivativeId(), 'content' => $plugin->build(),
  ];
  $markup = (string) \Drupal::service('renderer')->renderInIsolation($build);
  $check(str_contains($markup, 'class="site-branding-block"'), 'The Drupal/Canvas wrapper cannot provide the full parent width.');
  $check(str_contains($markup, 'data-component-id="clear_layers:site-branding"'), 'The branding block no longer renders its SDC.');

  foreach ([['left', 1, TRUE], ['center', 50, TRUE], ['right', 100, TRUE], ['invalid', 0, FALSE], ['invalid', 101, FALSE]] as [$alignment, $width, $valid]) {
    $typed = \Drupal::service('config.typed')->createFromNameAndData('clear_layers.settings', array_replace($before, ['branding_alignment' => $alignment, 'branding_width' => $width]));
    $check((count($typed->get('branding_alignment')->validate()) === 0) === $valid, 'Alignment schema validation is incorrect.');
    $check((count($typed->get('branding_width')->validate()) === 0) === $valid, 'Width schema validation is incorrect.');
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
}

$check(\Drupal::service('config.storage')->read('clear_layers.settings') === $before, 'The test changed saved theme settings.');
echo "PASS: Existing layout preserved, 18 alignment/width/text-placement combinations, form values, integer submission, invalid values, schema bounds and HTML cache tags. Saved settings unchanged.\n";
