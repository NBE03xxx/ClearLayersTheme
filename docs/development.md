# 開発と検証

[README に戻る](../README.md)

SDC ごとの Twig・CSS・JavaScript と、SCSS／JavaScript のソースを `components/` 内で管理します。Drupal の標準テンプレートは `templates/`、設定フォームは `theme-settings.php`、テーマの処理は `clear_layers.theme` にあります。

ビルドする場合はテーマディレクトリで実行します。Node.js の指定は `.nvmrc` を参照してください。

```sh
npm ci
npm run build
```

`npm run watch` のブラウザー連携を使う場合は `.env-example` を `.env` にコピーし、プロキシ先を開発環境に合わせて設定します。`.env` は Git で管理しません。

ビルド済みの CSS・JavaScript は配布に必要なため Git に含めます。`node_modules`、ソースマップ、ログ、バックアップ、AGENTS.md、実サイトの導入用データと個別移行スクリプトは `.gitignore` で除外しています。

明暗モードのソース検証:

```sh
node tests/color-scheme.mjs
```

Canvas のある Drupal サイトでのデータ診断（プロジェクト直下で実行）:

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/canvas-health.php
```

インストール済みテーマの SDC と Twig の読み込み検証:

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/theme-integrity.php
```

同梱背景画像の選択・プレビュー・保存値の検証（サイトの設定は保存しません）:

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/background-picker.php
```

標準ロゴの５色・背景との自動連動・手動選択・独自ロゴの優先・Canvas 表示・キャッシュ依存の検証（サイトの設定は保存しません）:

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/logo-colors.php
```

Core からコピーした標準テンプレートは Core 更新時に自動更新されません。`.info.yml` の `generator` を参照し、関連する Core の変更を確認して取り込んでください。公開済みの SDC の props／slots を変更する際は、Canvas の保存済みバージョンとの互換性を確認してください。
