<?php

namespace Drupal\clear_layers\Setup;

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\ComponentSource\ComponentSourceManager;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\PageVariant;
use Drupal\canvas\PageVariantMigration;
use Drupal\Core\Session\AccountInterface;

/**
 * Creates a generic Canvas starting layout without importing site content.
 */
final class CanvasSetup {

  public const TARGETS = [
    'pattern' => ['site_header', 'site_footer'],
    'page_variant' => ['site_layout', 'taxonomy_listing'],
  ];

  public const COMPONENTS = [
    'sdc.clear_layers.shared-site-section',
    'sdc.clear_layers.taxonomy-listing',
    'sdc.clear_layers.site-navigation',
    'sdc.clear_layers.site-header',
    'sdc.clear_layers.site-footer',
    'sdc.clear_layers.footer-menu',
    'sdc.clear_layers.color-scheme',
    'sdc.clear_layers.account-menu',
    'block.system_branding_block',
    'block.system_menu_block.footer',
    'marker.page_content',
  ];

  public static function hasAccess(AccountInterface $account): bool {
    foreach (['administer themes', 'administer modules', 'administer page template', 'administer patterns', 'administer components'] as $permission) {
      if (!$account->hasPermission($permission)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Returns prerequisites; checking them never writes configuration.
   */
  public static function blockers(): array {
    $blockers = [];
    foreach (['canvas' => 'Drupal Canvas 1.12 以降', 'taxonomy' => 'Taxonomy', 'views' => 'Views'] as $module => $label) {
      if (!\Drupal::moduleHandler()->moduleExists($module)) {
        $blockers[] = $label . ' を導入・有効化してください。';
      }
    }
    if (\Drupal::config('system.theme')->get('default') !== 'clear_layers') {
      $blockers[] = 'Clear Layers を既定テーマに設定してください。既存の Canvas テーマから移行する場合は、先に移行ガイドを確認してください。';
    }
    if (!class_exists(PageVariant::class) || !class_exists(PageVariantMigration::class)) {
      $blockers[] = 'この初期構成にはページテンプレート API を備えた Drupal Canvas 1.12 以降が必要です。';
    }
    if ($blockers) {
      return $blockers;
    }
    foreach (self::TARGETS as $type => $ids) {
      foreach (\Drupal::entityTypeManager()->getStorage($type)->loadMultiple($ids) as $entity) {
        if (!$entity->status() || count($entity->getTypedData()->validate())) {
          $blockers[] = $entity->label() . ' が無効または検証エラーのある状態です。Canvas で修正・公開してから実行してください。';
        }
        if ($type === 'pattern') {
          foreach ($entity->getComponentTree() as $item) {
            if ($item->component_id === 'sdc.clear_layers.shared-site-section') {
              $blockers[] = $entity->label() . ' に共通パターンの循環参照があります。';
            }
          }
        }
      }
    }
    // Read raw entries so staged translation drafts are checked as well.
    foreach (\Drupal::keyValue(AutoSaveManager::AUTO_SAVE_STORE)->getAll() as $entry) {
      $type = $entry['entity_type'] ?? '';
      $id = (string) ($entry['entity_id'] ?? '');
      $affected = isset(self::TARGETS[$type]) && in_array($id, self::TARGETS[$type], TRUE);
      foreach (self::TARGETS as $targetType => $ids) {
        foreach ($ids as $targetId) {
          if (str_contains(json_encode($entry, JSON_THROW_ON_ERROR), 'canvas.' . $targetType . '.' . $targetId)) {
            $affected = TRUE;
          }
        }
      }
      if ($affected) {
        $blockers[] = '共通パターンまたはテンプレートに未公開の変更があります。Canvas で公開または破棄してから実行してください。';
        break;
      }
    }
    return array_unique($blockers);
  }

  /**
   * Builds unsaved definitions from discovered versions, never site exports.
   */
  public static function definitions(array $versions): array {
    $node = static function (string $id, array $inputs = [], ?string $parent = NULL, ?string $slot = NULL) use ($versions): array {
      if (empty($versions[$id])) {
        throw new \RuntimeException('必要な Canvas コンポーネントがありません: ' . $id);
      }
      $item = [
        'uuid' => \Drupal::service('uuid')->generate(),
        'component_id' => $id,
        'component_version' => $versions[$id],
        'inputs' => $inputs,
      ];
      if ($parent !== NULL) {
        $item['parent_uuid'] = $parent;
        $item['slot'] = $slot;
      }
      return $item;
    };
    $header = $node('sdc.clear_layers.site-header', ['branding_width' => 60, 'branding_alignment' => 'left', 'actions_alignment' => 'right']);
    $footer = $node('sdc.clear_layers.site-footer', ['tag_columns' => 8, 'top_alignment' => 'left', 'left_alignment' => 'left', 'center_alignment' => 'left', 'right_alignment' => 'right', 'bottom_alignment' => 'left']);
    $footerMenu = $node('sdc.clear_layers.footer-menu', ['show_title' => FALSE, 'title_alignment' => 'left'], $footer['uuid'], 'footer_left');
    $common = static fn (string $section): array => $node('sdc.clear_layers.shared-site-section', ['section' => $section]);
    $taxonomy = $node('sdc.clear_layers.taxonomy-listing', ['show_title' => TRUE, 'title_alignment' => 'left', 'title_size' => 32]);
    return [
      'pattern' => [
        'site_header' => [
          'id' => 'site_header', 'label' => 'サイト共通ヘッダー', 'status' => TRUE,
          'component_tree' => [
            $header,
            $node('block.system_branding_block', ['label' => 'サイトブランディング', 'label_display' => '0', 'use_site_logo' => TRUE, 'use_site_name' => TRUE, 'use_site_slogan' => FALSE], $header['uuid'], 'branding'),
            $node('sdc.clear_layers.color-scheme', ['label' => '表示', 'system_label' => '自動', 'light_label' => 'ライト', 'dark_label' => 'ダーク'], $header['uuid'], 'actions'),
            $node('sdc.clear_layers.site-navigation', ['alignment' => 'left', 'left_spacing' => 0, 'right_spacing' => 0, 'menu_name' => 'main', 'toggle_label' => 'メニュー']),
          ],
        ],
        'site_footer' => [
          'id' => 'site_footer', 'label' => 'サイト共通フッター', 'status' => TRUE,
          'component_tree' => [
            $footer,
            $footerMenu,
            $node('block.system_menu_block.footer', ['label' => 'フッター', 'label_display' => '0', 'level' => 1, 'depth' => 1, 'expand_all_items' => FALSE], $footerMenu['uuid'], 'content'),
            $node('sdc.clear_layers.account-menu', ['show_title' => FALSE, 'title_alignment' => 'right', 'show_account_link' => FALSE], $footer['uuid'], 'footer_right'),
          ],
        ],
      ],
      'page_variant' => [
        'site_layout' => [
          'id' => 'site_layout', 'label' => 'サイト共通レイアウト', 'status' => TRUE,
          'description' => 'サイト共通ヘッダーとフッターの間にページの内容を表示します。',
          'component_tree' => [$common('header'), $node('marker.page_content'), $common('footer')],
        ],
        'taxonomy_listing' => [
          'id' => 'taxonomy_listing', 'label' => '分類・タグの一覧', 'status' => TRUE,
          'description' => '分類名と記事一覧をガラス調のパネルで表示します。',
          'component_tree' => [$common('header'), $taxonomy, $node('marker.page_content', [], $taxonomy['uuid'], 'content'), $common('footer')],
        ],
      ],
    ];
  }

  /**
   * Creates missing configuration and enables the bundled routing helper.
   */
  public static function create(bool $makeDefault = FALSE): array {
    if (!self::hasAccess(\Drupal::currentUser())) {
      throw new \RuntimeException('テーマ・モジュール・Canvas の部品・パターン・テンプレートを管理する権限が必要です。');
    }
    $lock = \Drupal::lock();
    if (!$lock->acquire('clear_layers.canvas_setup', 120)) {
      throw new \RuntimeException('別の初期構成処理が実行中です。完了後にもう一度お試しください。');
    }
    try {
      if ($blockers = self::blockers()) {
        throw new \RuntimeException(implode(' ', $blockers));
      }
      // Drupal discovers the helper in the theme directory; no file copying.
      $moduleList = \Drupal::service('extension.list.module');
      $moduleList->reset();
      $modules = $moduleList->getList();
      if (!isset($modules['clear_layers_canvas_taxonomy'])) {
        throw new \RuntimeException('同梱の Clear Layers Canvas Taxonomy が見つかりません。テーマ一式を配置し、キャッシュを再構築してください。');
      }
      // Generate only missing components; saved component versions are retained.
      $manager = \Drupal::service(ComponentSourceManager::class);
      $sdc = [];
      foreach (self::COMPONENTS as $id) {
        if (str_starts_with($id, 'sdc.') && !Component::load($id)) {
          $sdc[] = 'clear_layers:' . substr($id, strlen('sdc.clear_layers.'));
        }
      }
      if ($sdc) {
        $manager->generateComponents('sdc', $sdc);
      }
      $blocks = [];
      foreach (['block.system_branding_block' => 'system_branding_block', 'block.system_menu_block.footer' => 'system_menu_block:footer'] as $id => $plugin) {
        if (!Component::load($id)) {
          $blocks[] = $plugin;
        }
      }
      if ($blocks) {
        $manager->generateComponents('block', $blocks);
      }
      PageVariantMigration::ensurePageContentMarker();
      $versions = [];
      foreach (self::COMPONENTS as $id) {
        $component = Component::load($id);
        if (!$component || $component->getComponentSource()->isBroken()) {
          throw new \RuntimeException('必要な Canvas コンポーネントを利用できません: ' . $id);
        }
        $versions[$id] = $component->getActiveVersion();
      }
      $definitions = self::definitions($versions);
      $pending = [];
      $result = ['created' => [], 'retained' => [], 'default_changed' => FALSE];
      foreach ($definitions as $type => $items) {
        $storage = \Drupal::entityTypeManager()->getStorage($type);
        foreach ($items as $id => $values) {
          if ($storage->load($id)) {
            $result['retained'][] = $id;
            continue;
          }
          $entity = $storage->create($values);
          $violations = $entity->getTypedData()->validate();
          if (count($violations)) {
            $messages = [];
            foreach ($violations as $violation) {
              $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            throw new \RuntimeException($id . ' を生成できません。' . implode(' ', $messages));
          }
          $pending[$id] = $entity;
        }
      }
      foreach (self::COMPONENTS as $id) {
        $component = Component::load($id);
        if (!$component->status()) {
          $component->enable()->save();
        }
      }
      foreach ($pending as $id => $entity) {
        $entity->save();
        $result['created'][] = $id;
      }
      if (!\Drupal::moduleHandler()->moduleExists('clear_layers_canvas_taxonomy')) {
        \Drupal::service('module_installer')->install(['clear_layers_canvas_taxonomy']);
      }
      if ($makeDefault) {
        $settings = \Drupal::configFactory()->getEditable('canvas.settings');
        if ($settings->get('default_page_variant') !== 'site_layout') {
          $settings->set('default_page_variant', 'site_layout')->save();
          $result['default_changed'] = TRUE;
        }
      }
      return $result;
    }
    finally {
      $lock->release('clear_layers.canvas_setup');
    }
  }

}
