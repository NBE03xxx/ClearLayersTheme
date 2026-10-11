<?php

/**
 * @file
 * Functions to support clear_layers theme settings.
 */

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

require_once __DIR__ . '/includes/palettes.inc';
require_once __DIR__ . '/includes/background.inc';
require_once __DIR__ . '/includes/spacing.inc';
require_once __DIR__ . '/includes/glass.inc';

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

  $options = [];
  foreach (clear_layers_color_palettes() as $id => $palette) {
    $options[$id] = t($palette['label']);
  }
  $form['clear_layers_settings']['color_palette'] = [
    '#type' => 'select',
    '#title' => t('サイト全体の配色'),
    '#options' => $options,
    '#default_value' => clear_layers_resolve_palette($provider->getSetting('color_palette', 'clear_layers')),
    '#description' => t('Clear Layers の背景・パネル・文字・リンク・フォームに共通の配色です。保存すると、すべての訪問者に適用します。ライト／ダークはそれぞれの配色で自動調整されます。'),
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

  if (\Drupal::moduleHandler()->moduleExists('media')) {
    $bundles = [];
    foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $type) {
      if ($type->getSource()->getPluginId() === 'image') {
        $bundles[$type->id()] = $type->id();
      }
    }
    $selected = clear_layers_background_media($provider->getSetting('background_media_uuid', 'clear_layers'));
    $background = &$form['clear_layers_settings']['background'];
    $background = [
      '#type' => 'container',
      '#weight' => -9,
    ];
    $background['background_media_uuid'] = [
      '#type' => 'entity_autocomplete',
      '#title' => t('ページの背景画像'),
      '#target_type' => 'media',
      '#selection_handler' => 'default:media',
      '#selection_settings' => ['target_bundles' => $bundles],
      '#default_value' => $selected,
      '#disabled' => !$bundles,
      '#description' => t('メディアにアップロード済みの画像を、画像名で検索して候補から選んでください。公開済みの公開ファイルを使用できます。空にして保存すると、テーマ標準の青い背景に戻ります。保存後、サイト全体に適用されます。'),
      '#ajax' => [
        'callback' => 'clear_layers_background_preview_ajax',
        'event' => 'change',
        'wrapper' => 'clear_layers-background-preview',
      ],
    ];
    // AJAX preview follows the current input, including clearing the field.
    $input = $form_state->getUserInput();
    if (array_key_exists('background_media_uuid', $input)) {
      $id = EntityAutocomplete::extractEntityIdFromAutocompleteInput((string) $input['background_media_uuid']);
      $selected = $id ? \Drupal::entityTypeManager()->getStorage('media')->load($id) : NULL;
    }
    $background['preview'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'clear_layers-background-preview', 'aria-live' => 'polite'],
    ];
    $file = $selected && $selected->access('view') ? clear_layers_background_file($selected) : NULL;
    $preview_uri = $file ? $file->getFileUri() : \Drupal::service('extension.list.theme')->getPath('clear_layers') . '/images/backgrounds/waves-blue.png';
    $background['preview']['image'] = [
      '#theme' => 'image',
      '#uri' => $preview_uri,
      '#alt' => t('背景画像のプレビュー'),
      '#attributes' => ['style' => 'max-width: 360px; width: 100%; height: auto;'],
    ];
    $form['#validate'][] = 'clear_layers_background_validate';
    // Normalize the selection before Drupal saves the theme settings.
    $form['#submit'] = array_merge(['clear_layers_background_submit'], $form['#submit'] ?? []);
  }

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
 * Returns the preview without submitting the settings form.
 */
function clear_layers_background_preview_ajax(array &$form, FormStateInterface $form_state): array {
  return $form['clear_layers_settings']['background']['preview'];
}

/**
 * Rejects media that cannot serve a public, site-wide image.
 */
function clear_layers_background_validate(array &$form, FormStateInterface $form_state): void {
  $id = $form_state->getValue('background_media_uuid');
  if ($id) {
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($id);
    if (!$media || !$media->access('view') || !clear_layers_background_file($media)) {
      $form_state->setErrorByName('background_media_uuid', t('公開済みの画像メディアを選択してください。画像ファイルは公開ファイル領域に保存されている必要があります。'));
    }
  }
}

/**
 * Stores the media UUID through the standard theme settings submit handler.
 */
function clear_layers_background_submit(array &$form, FormStateInterface $form_state): void {
  $id = $form_state->getValue('background_media_uuid');
  $media = $id ? \Drupal::entityTypeManager()->getStorage('media')->load($id) : NULL;
  $form_state->setValue('background_media_uuid', $media ? $media->uuid() : '');
}
