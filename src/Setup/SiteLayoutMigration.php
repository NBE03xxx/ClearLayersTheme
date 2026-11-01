<?php

namespace Drupal\clear_layers\Setup;

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\PageVariant;
use Drupal\canvas\Entity\Pattern;

/**
 * Prepares the legacy shared sections for direct editing in the page template.
 *
 * Planning never saves configuration. Patterns are retained for older pages,
 * revisions and explicit exceptions; only the unused taxonomy outer template
 * is removed. Custom or translated taxonomy layouts require manual migration.
 */
final class SiteLayoutMigration {

  /**
   * Expands root-level references while preserving component inputs and UUIDs.
   */
  public static function expandSections(array $tree, array $patterns): array {
    $expanded = [];
    foreach ($tree as $row) {
      if ($row['component_id'] !== 'sdc.clear_layers.shared-site-section') {
        $expanded[] = $row;
        continue;
      }
      $section = $row['inputs']['section'] ?? 'header';
      if (!empty($row['parent_uuid']) || !isset($patterns[$section]) || !$patterns[$section]) {
        throw new \RuntimeException('共通部品の配置または参照先を確認してください。入れ子の参照は自動移行しません。');
      }
      foreach ($patterns[$section] as $child) {
        if ($child['component_id'] === 'sdc.clear_layers.shared-site-section') {
          throw new \RuntimeException('共通パターンに循環参照があります。');
        }
        $expanded[] = $child;
      }
    }
    $uuids = array_column($expanded, 'uuid');
    if (count($uuids) !== count(array_unique($uuids))) {
      throw new \RuntimeException('移行先の部品 UUID が重複します。共通部品の重複配置を確認してください。');
    }
    return $expanded;
  }

  /**
   * Returns validated, unsaved changes and original configuration for backup.
   */
  public static function plan(): array {
    if (\Drupal::config('system.theme')->get('default') !== 'clear_layers') {
      throw new \RuntimeException('Clear Layers が既定テーマのサイトで実行してください。');
    }
    // Do not overwrite drafts, including selections staged on other entities.
    if (\Drupal::keyValue(AutoSaveManager::AUTO_SAVE_STORE)->getAll()) {
      throw new \RuntimeException('Canvas に未公開の変更があります。公開または破棄してから再実行してください。');
    }
    $layout = PageVariant::load('site_layout');
    if (!$layout || !$layout->status()) {
      throw new \RuntimeException('有効な「サイト共通レイアウト」が必要です。');
    }
    $originals = ['canvas.page_variant.site_layout' => $layout->toArray()];
    $patterns = [];
    foreach (['header', 'footer'] as $section) {
      $pattern = Pattern::load('site_' . $section);
      if ($pattern) {
        $originals[$pattern->getConfigDependencyName()] = $pattern->toArray();
        self::assertUntranslated($pattern);
        if (!$pattern->status()) {
          throw new \RuntimeException($pattern->label() . ' が無効です。');
        }
        $patterns[$section] = array_values($pattern->get('component_tree'));
      }
    }
    self::assertUntranslated($layout);
    $tree = self::expandSections(array_values($layout->get('component_tree')), $patterns);
    $updated = clone $layout;
    $updated->setComponentTree($tree);
    $updates = [];
    if ($tree !== array_values($layout->get('component_tree'))) {
      $updated->set('description', 'ヘッダー・フッターをここで直接編集します。Page content には各ページの本文や一覧を表示します。');
      self::validate($updated);
      $updates[] = $updated;
    }

    $taxonomy = PageVariant::load('taxonomy_listing');
    if ($taxonomy) {
      self::assertUntranslated($taxonomy);
      self::assertStandardTaxonomyTree(array_values($taxonomy->get('component_tree')));
      // Deleting a selected variant would save pages or cascade-delete config.
      // Refuse instead: no page content or revision is changed by this tool.
      $manager = \Drupal::entityTypeManager();
      $pages = $manager->getStorage('canvas_page')->getQuery()->accessCheck(FALSE)
        ->condition('page_variant', 'taxonomy_listing')->count()->execute();
      if ($pages) {
        throw new \RuntimeException('分類用テンプレートを選択している Canvas ページを Site Default に変更してください。');
      }
      $storage = \Drupal::service('config.storage');
      foreach ($storage->listAll() as $name) {
        $config = $storage->read($name);
        if (($config['page_variant'] ?? NULL) === 'taxonomy_listing'
          || in_array('canvas.page_variant.taxonomy_listing', $config['dependencies']['config'] ?? [], TRUE)) {
          throw new \RuntimeException($name . ' が分類用テンプレートを参照しています。割り当てを変更してから再実行してください。');
        }
      }
      $originals[$taxonomy->getConfigDependencyName()] = $taxonomy->toArray();
    }
    $settings = \Drupal::config('canvas.settings');
    $originals['canvas.settings'] = $settings->getRawData();
    return [
      'updates' => $updates,
      'delete' => $taxonomy,
      'default_changed' => $settings->get('default_page_variant') !== 'site_layout',
      'originals' => $originals,
    ];
  }

  private static function assertUntranslated($entity): void {
    if ($entity->getTranslationLanguages(FALSE)) {
      throw new \RuntimeException($entity->label() . ' に翻訳があります。翻訳を含めた個別の移行が必要です。');
    }
  }

  private static function validate($entity): void {
    $violations = $entity->getTypedData()->validate();
    if (count($violations)) {
      throw new \RuntimeException($entity->label() . ': ' . (string) $violations);
    }
  }

  /**
   * Do not discard custom panels, titles, or additional taxonomy components.
   */
  public static function assertStandardTaxonomyTree(array $tree): void {
    if (array_column($tree, 'component_id') !== [
      'sdc.clear_layers.shared-site-section', 'sdc.clear_layers.taxonomy-listing',
      'marker.page_content', 'sdc.clear_layers.shared-site-section',
    ] || !empty($tree[0]['parent_uuid']) || !empty($tree[1]['parent_uuid'])
      || !empty($tree[3]['parent_uuid'])
      || ($tree[2]['parent_uuid'] ?? NULL) !== $tree[1]['uuid']
      || ($tree[2]['slot'] ?? NULL) !== 'content'
      || ($tree[0]['inputs']['section'] ?? 'header') !== 'header'
      || ($tree[3]['inputs']['section'] ?? 'header') !== 'footer') {
      throw new \RuntimeException('分類用テンプレートに独自の配置があります。本文側への個別の移行が必要です。');
    }
    $defaults = ['show_title' => TRUE, 'title_alignment' => 'left', 'title_size' => 32];
    if (array_replace($defaults, $tree[1]['inputs']) != $defaults) {
      throw new \RuntimeException('分類用パネルに独自の設定があります。Views 側の Twig に設定を移してから再実行してください。');
    }
  }

}
