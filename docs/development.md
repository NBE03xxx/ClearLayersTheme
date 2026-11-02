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

Canvas の初期構成と、移行済みの共通レイアウトの検証:

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/canvas-setup.php
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/site-layout.php
```

`canvas-setup.php` は、生成する外枠が `site_layout` 1つであること、ヘッダー・フッターを直接編集できること、Page content が1つであること、Canvas の schema と権限を検証します。必要なコンポーネントが登録済みのサイトで実行してください。

`site-layout.php` は、[v1.1.5以前の旧バージョンからの移行](upgrade-from-v1.1.5.md)が完了し、Canvas に未公開の変更がないサイトで実行します。移行の再実行で変更が発生しないこと、部品の入力値・親子関係の保持、重複 UUID・独自設定の検出、編集プレビューの部品選択情報、代表ページの共通部分とタグ一覧・RSS を確認します。保存済み設定・下書きは変更しません。実際のブラウザーでのクリック・保存・公開と画面幅ごとの表示は別途確認します。

Clear Layers の v1.1.5以前の旧バージョン由来の `theme_clear_layers` の削除と PageRegion の無効化は、[更新手順の整理操作](upgrade-from-v1.1.5.md#3-不要なテーマテンプレートを整理する)に従って行います。移行スクリプトの自動バックアップ、整理前の追加バックアップ、検証後の削除も同じ更新手順で説明しています。

Gin Login と公開側の Canvas レイアウトの検証:

```sh
vendor/bin/drush --uri=https://example.com php:script web/themes/custom/clear_layers/tests/login-layout.php
```

`--uri` は検証サイトの URL に置き換えます。`login-layout.php` は、Clear Layers が既定テーマ、Gin が管理テーマで、Gin Login と Clear Layers Canvas Taxonomy が有効、Canvas の既定ページテンプレートが `site_layout`、Gin Login の背景画像が設定済みのサイトで実行します。ログイン・パスワード再設定のフォーム、見出し、スキップリンク、背景画像、公開ページのヘッダー・フッターとテーマ別のテンプレート選択を確認します。フォーム送信・認証・メール送信は行わず、保存済み設定・Canvas の下書きが変わらないことを確認します。PC・スマートフォン幅の表示、横はみ出し、パスワード表示切替はブラウザーで別途確認してください。

管理テーマとの競合を防ぐ処理は、テーマ同梱補助モジュールの `AdminThemePageVariantSubscriber` にあります。Canvas がページ描画を選ぶ優先度 `-100` の後に、`-200` で必要な場合だけ Core の通常描画へ戻します。Core / Canvas / Gin Login の更新時には、このテストと共通レイアウトの検証を実行してください。

同梱背景画像の選択・プレビュー・保存値の検証（サイトの設定は保存しません）:

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/background-picker.php
```

標準ロゴの５色・背景との自動連動・手動選択・独自ロゴの優先・Canvas 表示・キャッシュ依存の検証（サイトの設定は保存しません）:

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/logo-colors.php
```

Core からコピーした標準テンプレートは Core 更新時に自動更新されません。`.info.yml` の `generator` を参照し、関連する Core の変更を確認して取り込んでください。公開済みの SDC の props／slots を変更する際は、Canvas の保存済みバージョンとの互換性を確認してください。
