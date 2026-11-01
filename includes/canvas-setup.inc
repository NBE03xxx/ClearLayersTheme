<?php

use Drupal\clear_layers\Setup\CanvasSetup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Adds an explicit setup action separate from saving appearance settings.
 */
function clear_layers_canvas_setup_form(array &$form): void {
  $form['clear_layers_canvas_setup'] = [
    '#type' => 'details',
    '#title' => t('Canvas の初期構成'),
    '#open' => FALSE,
    '#weight' => -15,
    '#access' => CanvasSetup::hasAccess(\Drupal::currentUser()),
  ];
  $section = &$form['clear_layers_canvas_setup'];
  $section['description'] = [
    '#type' => 'html_tag', '#tag' => 'p',
    '#value' => t('ヘッダー・Page content・フッターを含む「サイト共通レイアウト」を作成します。共通部分はこのテンプレートで直接編集し、記事・固定ページの本文はコンテンツテンプレート、検索・分類一覧は一覧側で設定します。Library で配置できる部品が0件のフォルダーを非表示にします。既存の同名構成は保持します。ページ・記事・メニュー項目・分類・Views は作成しません。v1.1.5以前の構成の移行手順はテーマの docs/upgrade-from-v1.1.5.md を参照してください。'),
  ];
  $blockers = CanvasSetup::blockers();
  if ($blockers) {
    $section['requirements'] = ['#theme' => 'item_list', '#items' => $blockers];
  }
  if (\Drupal::moduleHandler()->moduleExists('canvas') && class_exists(\Drupal\canvas\Entity\PageVariant::class)) {
    foreach (CanvasSetup::TARGETS as $type => $ids) {
      foreach ($ids as $id) {
        $entity = \Drupal::entityTypeManager()->getStorage($type)->load($id);
        if ($entity) {
          $section['existing_' . $id] = [
            '#type' => 'link',
            '#title' => t('@label を編集（既存の構成を保持）', ['@label' => $entity->label()]),
            '#url' => Url::fromRoute('canvas.boot.entity', ['entity_type' => $type, 'entity' => $id]),
            '#prefix' => '<p>', '#suffix' => '</p>',
          ];
        }
      }
    }
  }
  $section['clear_layers_setup_default'] = [
    '#type' => 'checkbox',
    '#title' => t('「サイト共通レイアウト」をサイトの既定テンプレートにする'),
    '#default_value' => FALSE,
    '#description' => t('テンプレート未指定のページにも共通ヘッダー・フッターを表示します。ページ内にヘッダー・フッターが配置されている場合は、先に整理して重複を避けてください。'),
  ];
  $section['create'] = [
    '#type' => 'submit',
    '#value' => t('Canvas の初期構成を作成'),
    '#name' => 'clear_layers_canvas_setup_create',
    '#submit' => ['clear_layers_canvas_setup_submit'],
    '#limit_validation_errors' => [],
    '#disabled' => (bool) $blockers,
  ];
  // The checkbox is an operation option, not an appearance setting.
  array_unshift($form['#submit'], 'clear_layers_canvas_setup_cleanup');
}

function clear_layers_canvas_setup_cleanup(array &$form, FormStateInterface $form_state): void {
  $form_state->unsetValue('clear_layers_setup_default');
}

function clear_layers_canvas_setup_submit(array &$form, FormStateInterface $form_state): void {
  try {
    // With limited validation, read only this button's explicit option from the
    // submitted input; normal theme-setting handlers must not run here.
    $makeDefault = !empty($form_state->getUserInput()['clear_layers_setup_default']);
    $result = CanvasSetup::create($makeDefault);
    \Drupal::messenger()->addStatus(t('Canvas の初期構成を用意しました。作成：@created、既存を保持：@retained。共通ヘッダー・フッターは「サイト共通レイアウト」で編集してください。Library の0件フォルダーの非表示が有効です。', [
      '@created' => count($result['created']), '@retained' => count($result['retained']),
    ]));
    if ($result['default_changed']) {
      \Drupal::messenger()->addStatus(t('サイトの既定テンプレートを「サイト共通レイアウト」に変更しました。'));
    }
  }
  catch (\Exception $exception) {
    \Drupal::logger('clear_layers')->error('Canvas setup failed: @message', ['@message' => $exception->getMessage()]);
    \Drupal::messenger()->addError(t('Canvas の初期構成を完了できませんでした：@message', ['@message' => $exception->getMessage()]));
  }
  $form_state->setRedirect('system.theme_settings_theme', ['theme' => 'clear_layers']);
}
