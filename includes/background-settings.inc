<?php

/**
 * @file
 * Thumbnail selection of images bundled in the theme's background directory.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Builds a filename picker and preserves legacy media until a new selection.
 */
function clear_layers_background_settings(?string $filename, ?string $uuid, FormStateInterface $form_state): array {
  $images = clear_layers_background_images();
  $input = $form_state->getUserInput();
  $selection = $input['background_image'] ?? ($filename ?: ($uuid ? '__legacy_media__' : ''));
  $selected = is_string($selection) ? ($images[$selection] ?? NULL) : NULL;
  $preview_uri = $selected['uri'] ?? clear_layers_background_default_uri();
  $label = $selected['label'] ?? t('テーマ標準の青い背景');
  if ($selection === '__legacy_media__' && ($media = clear_layers_background_media($uuid))) {
    if ($media->access('view') && ($file = clear_layers_background_file($media))) {
      $preview_uri = $file->getFileUri();
      $label = t('現在の背景：@name', ['@name' => $media->label()]);
    }
  }
  $element = [
    '#type' => 'fieldset',
    '#title' => t('ページの背景画像'),
    '#weight' => -9,
    '#attributes' => ['id' => 'clear_layers-background'],
    '#attached' => ['library' => ['clear_layers/background-settings']],
    'background_image' => [
      '#type' => 'hidden',
      '#default_value' => $selection,
    ],
    // Retain the UUID from server-side settings, never from submitted input.
    'background_media_uuid' => [
      '#type' => 'value',
      '#value' => $uuid ?? '',
    ],
    'description' => [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => t('「同梱の背景画像から選択」を開き、使いたい画像を選んでください。設定を保存するとサイト全体の背景に適用されます。'),
    ],
    'preview' => [
      '#type' => 'container',
      '#attributes' => ['class' => ['clear-layers-background-preview'], 'aria-live' => 'polite'],
      'image' => [
        '#theme' => 'image',
        '#uri' => $preview_uri,
        '#alt' => t('背景画像のプレビュー'),
      ],
      'name' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        'text' => ['#plain_text' => $label],
      ],
    ],
    'reset_background' => clear_layers_background_picker_button(t('テーマ標準の背景に戻す'), 'clear-layers-background-reset', ''),
    'picker' => [
      '#type' => 'details',
      '#title' => t('同梱の背景画像から選択'),
      '#open' => (bool) $form_state->get('clear_layers_background_picker_open'),
      'items' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['clear-layers-background-grid']],
      ],
    ],
  ];
  foreach ($images as $name => $image) {
    $button_name = 'clear-layers-background-select-' . substr(hash('sha256', $name), 0, 16);
    $card = [
      '#type' => 'container',
      '#attributes' => ['class' => ['clear-layers-background-card']],
      'image' => [
        '#theme' => 'image',
        '#uri' => $image['uri'],
        '#alt' => '',
        '#attributes' => ['loading' => 'lazy'],
      ],
      'name' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        'text' => ['#plain_text' => $image['label']],
      ],
      'select' => clear_layers_background_picker_button(t('選択'), $button_name, $name),
    ];
    $card['select']['#attributes']['aria-label'] = t('「@name」を背景画像に選択', ['@name' => $image['label']]);
    $element['picker']['items'][$name] = $card;
  }
  if (!$images) {
    $element['picker']['empty'] = ['#markup' => '<p>' . t('同梱の背景画像がありません。') . '</p>'];
  }
  return $element;
}

/**
 * Creates a selection action that never saves theme settings.
 */
function clear_layers_background_picker_button(mixed $label, string $name, string $filename): array {
  return [
    '#type' => 'submit',
    '#value' => $label,
    '#name' => $name,
    '#background_image' => $filename,
    '#submit' => ['clear_layers_background_picker_submit'],
    '#limit_validation_errors' => [],
    '#ajax' => [
      'callback' => 'clear_layers_background_preview_ajax',
      'wrapper' => 'clear_layers-background',
    ],
  ];
}

/**
 * Updates selection and preview while retaining unsaved form input.
 */
function clear_layers_background_picker_submit(array &$form, FormStateInterface $form_state): void {
  $filename = $form_state->getTriggeringElement()['#background_image'];
  if ($filename !== '' && !clear_layers_background_image_uri($filename)) {
    \Drupal::messenger()->addError(t('この背景画像が見つかりません。別の画像を選択してください。'));
    $form_state->set('clear_layers_background_picker_open', TRUE);
  }
  else {
    $input = $form_state->getUserInput();
    $input['background_image'] = $filename;
    $form_state->setUserInput($input);
    $form_state->setValue('background_image', $filename);
    $form_state->set('clear_layers_background_picker_open', FALSE);
  }
  $form_state->setRebuild();
}
