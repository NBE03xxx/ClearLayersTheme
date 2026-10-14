<?php

/**
 * @file
 * Color selection with light and dark previews for the default site logo.
 */

use Drupal\Component\Render\FormattableMarkup;

/**
 * Builds accessible radio options with a preview of each bundled logo.
 */
function clear_layers_logo_settings(mixed $selection): array {
  $options = ['auto' => t('背景画像に合わせて自動選択')];
  foreach (clear_layers_logo_colors() as $color => $definition) {
    $url = \Drupal::service('file_url_generator')->generateString(clear_layers_logo_uri($color));
    $options[$color] = new FormattableMarkup(
      '<span class="clear-layers-logo-option"><span>@label</span><span class="clear-layers-logo-samples"><span class="clear-layers-logo-sample"><img src="@url" alt="" width="64" height="64"><span>@light</span></span><span class="clear-layers-logo-sample clear-layers-logo-sample--dark"><img src="@url" alt="" width="64" height="64"><span>@dark</span></span></span></span>',
      ['@label' => $definition['label'], '@url' => $url, '@light' => t('明'), '@dark' => t('暗')],
    );
  }
  return [
    '#type' => 'radios',
    '#title' => t('標準ロゴの色'),
    '#options' => $options,
    '#default_value' => clear_layers_logo_selection($selection),
    '#description' => t('自動選択では、同梱の青・緑・赤・琥珀色・モノクロの背景に対応するロゴを表示します。それ以外の背景や以前のメディア背景では青になります。色を選ぶと背景に関係なくその色を使います。明暗モードに応じて見やすさを調整します。保存するとサイト全体と Canvas のブランディングブロックに適用されます。'),
    '#attributes' => ['class' => ['clear-layers-logo-colors']],
    '#states' => ['visible' => [':input[name="default_logo"]' => ['checked' => TRUE]]],
    '#attached' => ['library' => ['clear_layers/logo-settings']],
  ];
}
