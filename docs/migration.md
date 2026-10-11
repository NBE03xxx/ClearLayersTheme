# 旧テーマから Clear Layers への移行

Clear Layers は機械名 `clear_layers` の独立テーマです。旧テーマ `liquidglass_theme` を親テーマとして必要としません。透過・配色・明暗切り替え・Canvas 用 SDC・コンパクトなヘッダーなどの機能を引き継いでいます。

テーマの機械名変更に伴い、PHP 名前空間、Twig の SDC 参照、ライブラリ、設定、ブロックの ID も変更しています。標準背景は `images/backgrounds/waves-blue.png` へ移動しました。既存の CSS クラスとカスタムプロパティ、ブラウザーの明暗設定キーは、表示と設定を引き継ぐために維持しています。

## 計画と適用

以下のコマンドは Drupal プロジェクト直下で実行します。

1. データベースとアップロードファイルを、公開ディレクトリ外の保護された場所へバックアップします。
2. Canvas の未公開の自動保存を公開または解消します。適用中は編集と設定変更を止めてください。
3. 旧テーマを残した状態で、新テーマのファイルを `web/themes/custom/clear_layers` に配置します。
4. 読み取り専用の計画を確認します。

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/scripts/migrate-liquidglass.php -- plan
```

このスクリプトは Canvas を使用するサイト向けです。未解消の自動保存、過去または将来のコンテンツリビジョンに旧コンポーネントの参照がある場合は停止します。その場合はリビジョンも含めた移行方法を準備してから進めてください。旧コンポーネントの保存済みのスキーマ・バージョン・有効状態を保持し、ページ・パターン・コンテンツテンプレート・ページレイアウト・ページ領域の参照を移します。

5. 新テーマをインストールしてから、もう一度計画を確認します。

```sh
vendor/bin/drush theme:install clear_layers -y
vendor/bin/drush php:script web/themes/custom/clear_layers/scripts/migrate-liquidglass.php -- plan
```

6. 計画を確認してから適用します。

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/scripts/migrate-liquidglass.php -- apply
vendor/bin/drush cr
```

`apply` は Drupal API を使い、保存済みのテーマ設定、ロゴのパス、ブロック配置、Canvas の参照とライブラリの分類を移し、検証後に既定テーマを `clear_layers` に切り替えます。コンテンツ本文・公開状態・更新日時は保持します。旧テーマのアンインストールやファイル削除、補助モジュールの入れ替えは行いません。

すでに新テーマを編集・利用しているサイトでは、新テーマ側の設定・ブロック・同名コンポーネントが移行元で置き換わるため、そのまま適用しないでください。再実行を前提とするツールではありません。

## 確認と旧テーマの削除

```sh
node web/themes/custom/clear_layers/tests/color-scheme.mjs
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/theme-integrity.php
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/canvas-health.php
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/account-menu.php
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/footer-menu-editor.php
```

トップページ、記事、分類一覧、検索、ログイン、明暗モード、スマホのヘッダー、Canvas の編集・プレビュー・公開を確認します。Canvas のデータ診断と旧コンポーネントの利用監査が通るまで旧テーマを残してください。

確認後は「外観」で旧テーマをアンインストールしてから、旧テーマのディレクトリを削除できます。GitHub の旧リポジトリ削除は別の操作です。新リポジトリの取得と利用を確認してから行ってください。

移行後に元の状態へ戻す場合は、バックアップしたデータベースと旧テーマを復元します。既定テーマだけを旧テーマへ戻す操作では、移行した Canvas 参照は元に戻りません。
