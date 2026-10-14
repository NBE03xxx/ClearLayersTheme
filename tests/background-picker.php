<?php

/**
 * @file
 * Checks bundled background selection without saving theme settings.
 */

use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\Core\Template\Attribute;
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
$before = \Drupal::config('clear_layers.settings')->getRawData();
$overrides = $GLOBALS['config']['clear_layers.settings'] ?? NULL;
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
try {
  $builder = \Drupal::formBuilder();
  $state = new FormState();
  $form = $builder->buildForm(ThemeSettingsForm::class, $state->addBuildInfo('args', ['clear_layers']));
  $background = $form['clear_layers_settings']['background'];
  $images = clear_layers_background_images();
  $check((bool) $images, 'This test requires bundled background images.');
  $check(Element::children($background['picker']['items']) === array_keys($images), 'Picker does not match the image directory.');

  foreach ($images as $filename => $image) {
    $button = $background['picker']['items'][$filename]['select'];
    // Run actual Form API button submission; the settings save must not run.
    $state = (new FormState())->setRequestMethod('POST')->setValues([
      'background_image' => '',
      'page_gutter' => '31',
      'logo_color' => 'amber',
      $button['#name'] => $button['#value'],
    ])->setTriggeringElement($button);
    $builder->submitForm(ThemeSettingsForm::class, $state, 'clear_layers');
    $check(!$state->hasAnyErrors(), 'Selection failed validation.');
    $rebuilt = $builder->rebuildForm('system_theme_settings', $state);
    $selection = $rebuilt['clear_layers_settings']['background'];
    $check($selection['background_image']['#value'] === $filename, 'Selection was lost in form rebuild.');
    $check($selection['preview']['image']['#uri'] === $image['uri'], 'Preview did not follow selection.');
    $check($rebuilt['clear_layers_settings']['spacing']['page_gutter']['#value'] === '31', 'Unsaved spacing was lost.');
    $check($rebuilt['logo']['logo_color']['#value'] === 'amber', 'Unsaved logo color was lost.');
    $check(!$selection['picker']['#open'], 'Picker did not close after selection.');

    $normalization = (new FormState())->setValues(['background_image' => $filename, 'background_media_uuid' => 'old-media-uuid']);
    clear_layers_background_validate($rebuilt, $normalization);
    $check(!$normalization->hasAnyErrors(), 'Bundled filename was rejected.');
    clear_layers_background_submit($rebuilt, $normalization);
    $check($normalization->getValue('background_image') === $filename, 'Normalization changed the filename.');
    $check($normalization->getValue('background_media_uuid') === '', 'Bundled selection retained the previous media setting.');

    // Runtime settings overrides exercise CSS output without saving config.
    $GLOBALS['config']['clear_layers.settings']['background_image'] = $filename;
    \Drupal::configFactory()->reset('clear_layers.settings');
    $variables = ['html_attributes' => new Attribute()];
    clear_layers_preprocess_html($variables);
    $url = \Drupal::service('file_url_generator')->generateString($image['uri']);
    $check(str_contains($variables['html_attributes']['style']->value(), clear_layers_background_css_url($url)), 'Selected background was not applied to public HTML.');
    $check(in_array('config:clear_layers.settings', $variables['clear_layers_meta']['#cache']['tags'], TRUE), 'Background settings cache dependency is missing.');
  }

  $html = (string) \Drupal::service('renderer')->renderInIsolation($rebuilt);
  $check(!str_contains($html, 'data-autocomplete-path='), 'Text input remains.');
  $check(str_contains($html, 'clear-layers-background-grid'), 'Thumbnail grid is missing.');

  foreach (['../images/backgrounds/waves-blue.png', 'missing.png', 'https://example.com/image.png', '__legacy_media__', []] as $filename) {
    $invalid = (new FormState())->setValues(['background_image' => $filename, 'background_media_uuid' => '']);
    clear_layers_background_validate($rebuilt, $invalid);
    $check($invalid->hasAnyErrors(), 'Invalid filename was accepted.');
    $invalid->clearErrors();
  }
  // A previous media selection is retained on unrelated settings saves.
  $uuid = ($before['background_media_uuid'] ?? '') ?: '00000000-0000-4000-8000-000000000001';
  $legacy = clear_layers_background_settings(NULL, $uuid, new FormState());
  $check($legacy['background_image']['#default_value'] === '__legacy_media__', 'Previous media setting was not retained.');
  $legacy_state = (new FormState())->setValues(['background_image' => '__legacy_media__', 'background_media_uuid' => $uuid]);
  clear_layers_background_validate($rebuilt, $legacy_state);
  $check(!$legacy_state->hasAnyErrors(), 'Previous media setting failed validation.');
  clear_layers_background_submit($rebuilt, $legacy_state);
  $check($legacy_state->getValue('background_image') === '' && $legacy_state->getValue('background_media_uuid') === $uuid, 'Previous media setting was overwritten.');

  $reset = (new FormState())->setRequestMethod('POST')->setValues([
    'background_image' => array_key_first($images),
    'page_gutter' => '31',
    'clear-layers-background-reset' => $background['reset_background']['#value'],
  ])->setTriggeringElement($background['reset_background']);
  $builder->submitForm(ThemeSettingsForm::class, $reset, 'clear_layers');
  $restored = $builder->rebuildForm('system_theme_settings', $reset);
  $check($restored['clear_layers_settings']['background']['background_image']['#value'] === '', 'Reset did not clear selection.');
  $check($restored['clear_layers_settings']['background']['preview']['image']['#uri'] === clear_layers_background_default_uri(), 'Reset did not restore the default preview.');
  clear_layers_background_submit($restored, $reset);
  $check($reset->getValue('background_media_uuid') === '', 'Reset retained the old media setting.');

  $GLOBALS['config']['clear_layers.settings']['background_image'] = 'missing.png';
  \Drupal::configFactory()->reset('clear_layers.settings');
  $variables = ['html_attributes' => new Attribute()];
  clear_layers_preprocess_html($variables);
  $url = \Drupal::service('file_url_generator')->generateString(clear_layers_background_default_uri());
  $check(str_contains($variables['html_attributes']['style']->value(), clear_layers_background_css_url($url)), 'Missing image did not fall back to the bundled default.');
  echo 'PASS: ' . count($images) . " bundled backgrounds, Form API selection/reset, preview, CSS output/cache tags, unsaved spacing, invalid paths, missing-file fallback and legacy preservation.\n";
}
finally {
  if ($overrides === NULL) {
    unset($GLOBALS['config']['clear_layers.settings']);
  }
  else {
    $GLOBALS['config']['clear_layers.settings'] = $overrides;
  }
  \Drupal::configFactory()->reset('clear_layers.settings');
  $switcher->switchBack();
}
$check(\Drupal::config('clear_layers.settings')->getRawData() === $before, 'Picker actions saved settings prematurely.');
echo "PASS: Saved settings unchanged.\n";
