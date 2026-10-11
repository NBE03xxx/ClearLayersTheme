# Clear Layers

Drupal 11 用の、半透明のパネル・柔らかなぼかし・重なりによる奥行きを備えた独立したカスタムテーマです。Drupal Core Starterkit を基礎とし、親テーマを必要としません。

![Clear Layers の標準背景](images/backgrounds/waves-blue.png)

- 機械名: `clear_layers`
- バージョン: `1.0.0`
- 検証環境: Drupal 11.4.9 / Canvas 1.12.0
- 親テーマ: なし（`base theme: false`）

## 主な機能

- 青系を中心とした配色、透過、背景のぼかし、奥行き。
- Ocean などの配色、メディア背景画像、左右余白・ブロック間隔をテーマ設定で調整。
- 訪問者の OS 設定に従う明暗モードと、小さな切り替えボタン。
- Canvas で利用できる SDC。ナビゲーション、ヘッダー、フッター、Hero、記事ヘッダー、カード、本文、一覧など。
- ブログ本文・コードブロックの明暗対応、長いコードの横スクロール。
- カード全体を通常のリンクとしてクリック可能。キーボード・Ctrl／中クリックに対応。
- サイト共通ヘッダー・フッターを Canvas パターンから参照する部品。
- テーマ設定の折りたたみプレビュー。
- 小さい画面では高さを抑えたヘッダーと、アイコンで開閉する検索パネル。
- テーマの配色と質感に合わせた公開操作パネル・ステータスメッセージ。

## インストール

通常のカスタムテーマと同様に、ファイルをテーマディレクトリへ配置し、管理画面からインストールできます。ビルド済み CSS・JavaScript を含むため、利用するだけなら Node.js は不要です。

旧テーマから移行する場合は、先に [移行手順](docs/migration.md) を確認してください。既定テーマの変更だけでは、Canvas の保存済みコンポーネント参照やブロック配置は移行されません。

Drupal プロジェクト直下で実行する例:

```sh
git clone https://github.com/NBE03xxx/ClearLayersTheme.git web/themes/custom/clear_layers
composer require 'drupal/twig_field_value:^2.0' 'drupal/twig_tweak:^3.2'
```

1. 管理画面の「機能拡張」（`/admin/modules`）で **Twig Field Value** と **Twig Tweak** を有効にします。
2. 「外観」（`/admin/appearance`）で **Clear Layers** を「インストールしてデフォルトに設定」します。
3. 「設定」（`/admin/appearance/settings/clear_layers`）で配色・背景・余白などを調整します。

Drush を使う場合:

```sh
vendor/bin/drush en twig_field_value twig_tweak -y
vendor/bin/drush theme:install clear_layers -y
vendor/bin/drush config:set system.theme default clear_layers -y
vendor/bin/drush cr
```

公開ディレクトリが `web` 以外の場合は、そのディレクトリ内の `themes/custom/clear_layers` に配置してください。GitHub の ZIP を使う場合も、展開したディレクトリ名を `clear_layers` にします。

## 初期設定

`config/install/clear_layers.settings.yml` に保存した初期値を、新規インストール時に使います。

| 設定 | 初期値 |
| --- | --- |
| 配色 | Ocean |
| 透過率 | 40% |
| ぼかし | 12px |
| 奥行き | 標準 |
| 左右余白 | 12px |
| ブロック間隔 | 24px |
| 背景 | 同梱の青い背景画像 |
| 明暗モード | 訪問者の OS 設定 |

既存サイトの保存済み設定は、テーマの更新だけでは上書きしません。テーマ設定のプレビューは初期状態では閉じており、クリックで展開します。保存済み設定と現在のトップページを表示します。

## 自鯖ロゴ

背景透過の「自鯖」ロゴを `images/branding/jisaba-life-logo-v2.png` に同梱しています。使う場合は、テーマ設定（`/admin/appearance/settings/clear_layers`）のロゴ画像でデフォルト画像の使用をオフにし、画像パスを `themes/custom/clear_layers/images/branding/jisaba-life-logo-v2.png` に設定します。

このロゴはライトモードでは元の青・紺・緑で、ダークモードでは CSS フィルターによって白く表示します。透明部分は維持し、OS の設定とサイトの表示切り替えに連動します。他のファイル名のロゴにはこのフィルターを適用しません。

ロゴだけを表示する場合は、ブランディングブロックの「サイトのロゴ」を有効にし、「サイト名」を無効にします。Canvas の共通ヘッダーを使っている場合は、そのパターン内のブランディングブロックで設定してください。既存サイトのロゴ設定や共通パターンは、テーマ更新だけでは変更しません。

## Canvas とページレイアウト

Canvas はテーマ単体の必須依存ではありません。Canvas で部品を編集する場合は Canvas を別途導入し、使う SDC をライブラリで有効にしてください。

テーマのインストールだけでは、ブログのコンテンツタイプ、Views、ページ、メニュー、Canvas テンプレートや共通パターンは作成されません。ページ全体のレイアウトと、記事本文・一覧カードのコンテンツテンプレートは、Canvas 側で個別に設定します。

共通ヘッダー・フッターの参照部品（`shared-site-section`）は `site_header` と `site_footer` という ID の Canvas パターンを読み込みます。利用先にこれらのパターンを用意する必要があります。共通パターンを編集・公開すると、それを参照しているページに反映されます。ページ側の編集画面からは参照部品の設定にあるリンクで編集元を開きます。

フッターにログイン／ログアウトのリンクを追加する場合は、共通フッターのパターン内に **ログイン・ログアウトメニュー**（`account-menu`）を配置します。匿名時はログイン、ログイン中はログアウトを表示します。タイトルの上書き・表示・位置と、**ユーザーアカウント情報を表示**を設定できます。アカウント情報は初期状態で非表示です。オンにすると、アクセス権のある利用者にはアカウント情報へのリンクも表示します。Drupal 標準のアクセス制御とログアウトのトークンを使用し、サイトのメニュー設定は変更しません。

他のメニューは **フッターメニュー（タイトル設定）** の内側に標準メニューブロックを配置すると、同じタイトル設定とデザインを利用できます。標準ブロック自体のタイトル設定は Canvas では省略されます。標準メニューブロックの追加時に `Add a CSS class to ancestors of the current page` のエラーが出る場合は、内側のブロックで開始階層と表示階層をともに `1` にするか、そのチェック項目を有効にしてください。専用の `account-menu` 部品では、この階層フォームを使用しません。

Canvas のプレビューでは、内側のメニューが入っていても親部品を選択できるよう、外側に **フッターメニュー（タイトル設定）** の帯と余白を表示します。帯をクリックしてタイトル設定を編集するか、左のレイヤー一覧から親部品を選択してください。この編集用の帯と余白は公開ページには表示しません。

`article-card` の任意の整数 prop `date` に記事の作成日時を渡すと、投稿日時を表示します。既存の日時なしの配置との互換性を維持しています。本文を `content-text` に渡すと、コードブロックを含む明暗モードのスタイルが適用されます。

初期設定と、任意のサイトデータの書き出し・導入については [starter-kit/README.md](starter-kit/README.md) を参照してください。実サイトのページ本文・構成図・設定の書き出しは、この公開リポジトリには含めていません。

## 開発

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

Core からコピーした標準テンプレートは Core 更新時に自動更新されません。`.info.yml` の `generator` を参照し、関連する Core の変更を確認して取り込んでください。公開済みの SDC の props／slots を変更する際は、Canvas の保存済みバージョンとの互換性を確認してください。

## ライセンスと由来

GNU General Public License の本文を [LICENSE.txt](LICENSE.txt) に同梱しています。Drupal Core Starterkit を基礎とし、従来のテーマで開発した SDC・ビルドツールを引き継いでいます。既存コードの著作権・ライセンス表記は保持しています。

## 背景画像の制作経緯

同梱の標準背景画像 `images/backgrounds/waves-blue.png` は、Drupal をイメージし、ChatGPT の画像生成機能を使用して作成しました。Drupal の公式提供画像ではありません。
