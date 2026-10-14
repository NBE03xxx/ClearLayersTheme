<?php

/**
 * @file
 * Functions to support clear_layers theme settings.
 */

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

require_once __DIR__ . '/includes/palettes.inc';
require_once __DIR__ . '/includes/background.inc';
require_once __DIR__ . '/includes/background-settings.inc';
require_once __DIR__ . '/includes/logo.inc';
require_once __DIR__ . '/includes/logo-settings.inc';
require_once __DIR__ . '/includes/spacing.inc';
require_once __DIR__ . '/includes/glass.inc';
require_once __DIR__ . '/includes/canvas-setup.inc';

/**
 * Implements hook_form_FORM_ID_alter() for system_theme_settings.
 */
function clear_layers_form_system_theme_settings_alter(&$form, FormStateInterface $form_state) {
  // ThemeSettingsForm calls this explicitly; the active theme may also invoke
  // it during normal form alteration. Register fields and handlers only once.
  if (($form['config_key']['#value'] ?? '') !== 'clear_layers.settings' || !empty($form['#clear_layers_settings_processed'])) {
    return;
  }
  $form['#clear_layers_settings_processed'] = TRUE;
  /** @var \Drupal\Core\Extension\ThemeSettingsProvider $provider */
  $provider = \Drupal::service(ThemeSettingsProvider::class);

  if (isset($form['logo'])) {
    $form['logo']['#title'] = t('サイトロゴ');
    $form['logo']['default_logo']['#title'] = t('テーマ標準のロゴを使用する');
    $form['logo']['logo_color'] = clear_layers_logo_settings($provider->getSetting('logo_color', 'clear_layers'));
  }

  $front_url = Url::fromRoute('<front>');
  $form['clear_layers_preview'] = [
    '#type' => 'details',
    '#title' => t('テーマのプレビュー'),
    '#open' => FALSE,
    '#weight' => -20,
  ];
  $form['clear_layers_preview']['description'] = [
    '#type' => 'html_tag',
    '#tag' => 'p',
    '#value' => t('保存済みのテーマ設定でトップページを表示します。設定を変更した後は保存して確認してください。明暗モードは、このブラウザーの選択に従います。'),
  ];
  $form['clear_layers_preview']['open'] = [
    '#type' => 'link',
    '#title' => t('サイトのプレビューを別タブで開く'),
    '#url' => $front_url,
    '#attributes' => ['target' => '_blank', 'rel' => 'noopener'],
    '#prefix' => '<p>',
    '#suffix' => '</p>',
  ];
  $form['clear_layers_preview']['site'] = [
    '#type' => 'html_tag',
    '#tag' => 'iframe',
    '#attributes' => [
      'src' => $front_url->toString(),
      'title' => t('Clear Layers のトップページプレビュー'),
      'loading' => 'lazy',
      'width' => '100%',
      'height' => '640',
      'style' => 'display: block; width: 100%; height: 640px; border: 1px solid #64748b; border-radius: 12px;',
    ],
  ];

  $form['clear_layers_settings'] = [
    '#type' => 'details',
    '#title' => t('Settings'),
    '#open' => TRUE,
    '#weight' => -10,
  ];
  $form['clear_layers_settings']['display_back_to_top'] = [
    '#type' => 'checkbox',
    '#title' => t('Enable back to top button'),
    '#default_value' => $provider->getSetting('display_back_to_top'),
    '#description' => t('Display a back to top button on the site.'),
  ];
  $form['clear_layers_settings']['branding_text_position'] = [
    '#type' => 'select',
    '#title' => t('ロゴと文字の配置'),
    '#options' => [
      'right' => t('ロゴの右に文字を表示'),
      'below' => t('ロゴの下に文字を表示'),
    ],
    '#default_value' => $provider->getSetting('branding_text_position', 'clear_layers') === 'below' ? 'below' : 'right',
    '#description' => t('サイト名とスローガンの位置を選択します。初期値は「右」です。Canvas の共通ヘッダーにも適用します。「右」では狭い画面でも横並びを保ち、長い文字は文字の領域内で折り返します。ロゴやサイト名を表示するかどうかは、ブランディングブロックで設定してください。'),
    '#weight' => -7,
  ];

  $options = [];
  foreach (clear_layers_color_palettes() as $id => $palette) {
    $options[$id] = t($palette['label']);
  }
  $form['clear_layers_settings']['color_palette'] = [
    '#type' => 'select',
    '#title' => t('サイト全体の配色'),
    '#options' => $options,
    '#default_value' => clear_layers_resolve_palette($provider->getSetting('color_palette', 'clear_layers')),
    '#description' => t('青・緑・赤・琥珀色・モノクロをもとにした配色です。パネル・文字・リンク・フォームなどに共通で適用します。背景画像は「ページの背景画像」で選択してください。保存すると、すべての訪問者に適用します。ライト／ダークはそれぞれの配色で自動調整されます。'),
    '#weight' => -10,
  ];

  $form['clear_layers_settings']['glass'] = [
    '#type' => 'details',
    '#title' => t('Clear Layers の質感'),
    '#open' => TRUE,
    '#weight' => -9,
  ];
  $glass_labels = [
    'glass_transparency' => t('ガラス面の透過率（％）'),
    'glass_blur' => t('背景のぼかし（px）'),
  ];
  $glass_descriptions = [
    'glass_transparency' => t('0％で不透明、100％で面の色が透明になります。初期値は30％です。ヘッダー・ナビゲーション・Hero・記事カード・フッターなどのガラス面に共通で適用します。文字や画像の濃さは変わりません。'),
    'glass_blur' => t('0pxでぼかしなし、数値を大きくすると背後の画像がぼけます。初期値は12pxです。背景の模様を見せたい場合は小さくしてください。'),
  ];
  foreach (clear_layers_glass_settings() as $key => $definition) {
    $form['clear_layers_settings']['glass'][$key] = [
      '#type' => 'number',
      '#title' => $glass_labels[$key],
      '#description' => $glass_descriptions[$key],
      '#default_value' => clear_layers_glass_value($key, $provider->getSetting($key, 'clear_layers')),
      '#min' => 0,
      '#max' => $definition['max'],
      '#step' => 1,
      '#required' => TRUE,
    ];
  }
  $depth_options = [];
  foreach (clear_layers_glass_depths() as $key => $label) {
    $depth_options[$key] = t($label);
  }
  $form['clear_layers_settings']['glass']['glass_depth'] = [
    '#type' => 'select',
    '#title' => t('ガラス面の奥行き'),
    '#options' => $depth_options,
    '#default_value' => clear_layers_glass_depth($provider->getSetting('glass_depth', 'clear_layers')),
    '#description' => t('影と縁の光で、背景から浮かぶような奥行きを付けます。初期値は「標準」です。「なし」は平面的に、「強め」は影を広く濃く表示します。ヘッダー・ナビゲーション・Hero・記事カード・フッターなどのガラス面に共通で適用します。'),
    '#required' => TRUE,
  ];
  $form['#submit'] = array_merge(['clear_layers_glass_submit'], $form['#submit'] ?? []);

  $form['clear_layers_settings']['spacing'] = [
    '#type' => 'details',
    '#title' => t('ページの余白'),
    '#open' => TRUE,
    '#weight' => -8,
  ];
  foreach (['block_spacing' => t('ブロック間の余白（px）'), 'page_gutter' => t('ページ左右の余白（px）')] as $key => $title) {
    $form['clear_layers_settings']['spacing'][$key] = [
      '#type' => 'number',
      '#title' => $title,
      '#default_value' => clear_layers_spacing_value($provider->getSetting($key, 'clear_layers')),
      '#min' => 0,
      '#max' => 64,
      '#step' => 1,
      '#required' => TRUE,
      '#description' => t('初期値は24pxです。0〜64pxで指定できます。狭い画面では指定値の約3分の2になります。ヘッダー内や記事一覧の内部配置には適用しません。'),
    ];
  }
  $form['clear_layers_settings']['spacing']['page_gutter']['#description'] = t('Canvas ページの表示領域の左右から取る余白です。初期値は24pxで、0〜64pxで指定できます。狭い画面では指定値の約3分の2になります。管理ツールバーや、各ブロックの内部余白には適用しません。');
  $form['#submit'] = array_merge(['clear_layers_spacing_submit'], $form['#submit'] ?? []);

  $form['clear_layers_settings']['background'] = clear_layers_background_settings(
    $provider->getSetting('background_image', 'clear_layers'),
    $provider->getSetting('background_media_uuid', 'clear_layers'),
    $form_state,
  );
  $form['#validate'][] = 'clear_layers_background_validate';
  $form['#submit'] = array_merge(['clear_layers_background_submit'], $form['#submit'] ?? []);

  clear_layers_canvas_setup_form($form);

}

/**
 * Normalizes glass settings before the standard theme settings save.
 */
function clear_layers_glass_submit(array &$form, FormStateInterface $form_state): void {
  foreach (clear_layers_glass_settings() as $key => $definition) {
    $form_state->setValue($key, clear_layers_glass_value($key, $form_state->getValue($key)));
  }
  $form_state->setValue('glass_depth', clear_layers_glass_depth($form_state->getValue('glass_depth')));
}

/**
 * Normalizes spacing to integers before the standard theme settings save.
 */
function clear_layers_spacing_submit(array &$form, FormStateInterface $form_state): void {
  foreach (['block_spacing', 'page_gutter'] as $key) {
    $form_state->setValue($key, clear_layers_spacing_value($form_state->getValue($key)));
  }
}

/**
 * Returns the selection preview and picker without saving theme settings.
 */
function clear_layers_background_preview_ajax(array &$form, FormStateInterface $form_state): array {
  return $form['clear_layers_settings']['background'];
}

/**
 * Rejects filenames outside the theme background image list.
 */
function clear_layers_background_validate(array &$form, FormStateInterface $form_state): void {
  $filename = $form_state->getValue('background_image');
  if ($filename !== '' && !clear_layers_background_image_uri($filename)
    && !($filename === '__legacy_media__' && $form_state->getValue('background_media_uuid'))) {
    $form_state->setErrorByName('background_image', t('テーマに同梱された背景画像を選択してください。'));
  }
}

/**
 * Saves the filename and retires the previous media setting on selection.
 */
function clear_layers_background_submit(array &$form, FormStateInterface $form_state): void {
  if ($form_state->getValue('background_image') === '__legacy_media__') {
    $form_state->setValue('background_image', '');
  }
  else {
    $form_state->setValue('background_media_uuid', '');
  }
}
