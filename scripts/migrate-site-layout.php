<?php

/**
 * @file
 * drush php:script .../scripts/migrate-site-layout.php -- plan|apply [backup.json]
 * Run plan first. Apply requires a new backup path outside the web root/repo.
 */

use Drupal\clear_layers\Setup\SiteLayoutMigration;

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}
$mode = $extra[0] ?? 'plan';
if (!in_array($mode, ['plan', 'apply'], TRUE)) {
  throw new InvalidArgumentException('Use plan or apply [absolute backup path].');
}
$lock = \Drupal::lock();
if (!$lock->acquire('clear_layers.canvas_setup', 120)) {
  throw new RuntimeException('別のレイアウト更新処理が実行中です。');
}
try {
  $plan = SiteLayoutMigration::plan();
  $summary = [
    'mode' => $mode,
    'update' => array_map(static fn ($entity) => $entity->getConfigDependencyName(), $plan['updates']),
    'delete_unused_template' => $plan['delete']?->id(),
    'set_default' => $plan['default_changed'] ? 'site_layout' : NULL,
    'legacy_patterns' => 'retained; edit header/footer in site_layout after migration',
    'page_content_and_views' => 'unchanged',
  ];
  if ($mode === 'apply' && ($plan['updates'] || $plan['delete'] || $plan['default_changed'])) {
    $backup = $extra[1] ?? '';
    $directory = realpath(dirname($backup));
    $project = realpath(dirname(DRUPAL_ROOT));
    if (!str_starts_with($backup, '/') || !$directory
      || $directory === $project || str_starts_with($directory, $project . '/')) {
      throw new RuntimeException('バックアップ先は公開ディレクトリ・プロジェクト外の絶対パスにしてください。');
    }
    $previousMask = umask(0077);
    try {
      $stream = fopen($backup, 'x');
    }
    finally {
      umask($previousMask);
    }
    if (!$stream) {
      throw new RuntimeException('新しいバックアップファイルを作成できません。');
    }
    $json = json_encode(['configuration' => $plan['originals']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    try {
      if (fwrite($stream, $json) !== strlen($json) || !fflush($stream)) {
        throw new RuntimeException('バックアップを保存できません。設定は変更していません。');
      }
    }
    finally {
      fclose($stream);
    }
    foreach ($plan['updates'] as $entity) {
      $entity->save();
    }
    if ($plan['default_changed']) {
      \Drupal::configFactory()->getEditable('canvas.settings')->set('default_page_variant', 'site_layout')->save();
    }
    $plan['delete']?->delete();
    $summary['backup'] = $backup;
  }
  print json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
finally {
  $lock->release('clear_layers.canvas_setup');
}
