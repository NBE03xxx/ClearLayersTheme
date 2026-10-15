# Clear Layers の初期設定と導入用データ

## テーマだけを導入する場合

通常のカスタムテーマと同様に、テーマディレクトリを公開ディレクトリ内の `themes/custom/clear_layers` に保存し、必要な Twig Field Value・Twig Tweak を有効にして、管理画面の「外観」からインストール・既定テーマに設定します。詳しい手順は [テーマの README](../README.md#インストール) を参照してください。Recipe の適用は不要です。

配色・背景・透過・ぼかし・奥行き・余白などの初期値は `config/install/clear_layers.settings.yml` に保存しています。新しいサイトでテーマを新規インストールしたときに読み込まれます。既存サイトの保存済み設定は更新しません。

v1.1.0 では、12種類の配色と5色の同梱背景をテーマ設定から選択できます。背景は `background/` の画像を使い、メディアへの登録や Recipe の適用は不要です。配色と背景画像は別々に設定します。以前のメディア背景は、同梱画像を選んで保存するまで維持します。詳細は [配色・背景の設定](../README.md#初期設定) と [v1.1.0 のリリースノート](../docs/releases/v1.1.0.md) を参照してください。

Canvas のページやメニュー、レイアウト、コンテンツタイプは、テーマだけでは作成されません。

**新規サイトで Canvas の共通構成を作る場合は、[README の Canvas 初期構成](../README.md#新規サイトの-canvas-初期構成)を使ってください。** テーマ設定のボタンで、汎用的な共通ヘッダー・フッターとページテンプレートを作成し、分類ページ用補助モジュールと Canvas Library Display を有効化します。下記のサイトデータの書き出しや Recipe は不要です。

## 公開リポジトリに含めるもの

- テーマ本体と、一般的なテーマ初期設定。
- 書き出し・適用スクリプト `scripts/export-starter-kit.php` と `scripts/apply-starter-kit.php`。
- 分類一覧の PageVariant 割り当て用補助モジュール `modules/clear_layers_canvas_taxonomy`。
- Library の配置可能な部品が0件のフォルダーを非表示にする `modules/canvas_library_display`。
- Canvas コンポーネントの保存済み版を復元する導入用補助モジュール `modules/clear_layers_starter_kit`。保守元は `starter-kit-module/` です。

**実サイトの `recipe/` と `manifest.yml` は Git に含めません。** 書き出しにはページ本文、メニュー、分類、画像、Views、フィールド、Canvas のテンプレートなどが含まれます。サイト固有の説明・構成図・設定が公開されることを避けるため、ローカルの導入用データとして管理します。GitHub からテーマを取得しても、5ページやサイトのレイアウトが自動で付属するわけではありません。

補助モジュールは、Canvas を使わずテーマだけを導入する場合にはインストール不要です。新規 Canvas 初期構成では、テーマ内の `clear_layers_canvas_taxonomy` と `canvas_library_display` をボタンから有効化します。Drupal はテーマ配下でもモジュールを検出するため、コピーは不要です。サイトデータの復元用 `clear_layers_starter_kit` は、下記の Recipe を利用する場合にのみ必要です。

## サイトの公開済み状態を保存する場合

現在の書き出しスクリプトは、元の構成で使用する Canvas パターン・テンプレート・Views と、保存対象のページ ID を指定しています。別サイトで使う場合は **先に対象 ID と設定名をそのサイトに合わせて確認・調整** してください。公開済みの状態を読み、ユーザーアカウント・記事・過去のリビジョン・下書き・非公開ファイルを除外します。公開済みの本文・画像も公開許可があるとは限らないため、生成結果を確認してから配布してください。

Drupal プロジェクト直下で読み取り専用の計画を確認します。

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/scripts/export-starter-kit.php -- plan
```

既存の `starter-kit/recipe/` と `starter-kit/manifest.yml` がある場合は、サイト外の保護された保存先へ退避します。既存の Recipe は上書きしません。

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/scripts/export-starter-kit.php -- export
```

`export` は `starter-kit/recipe/` に Core Recipe／Default Content 形式のデータを作り、`manifest.yml` に依存モジュール・バージョン・チェックサムを記録します。テーマの初期設定ファイルも更新します。書き出し元のデータベースへは保存しません。

生成された `recipe/` と `manifest.yml` は `.gitignore` の対象です。元サイトでは引き続きローカルに保存できます。テーマのソースや初期設定の変更だけを公開するときは、これらを Git に追加しないでください。

## 保存したデータを別サイトへ導入する場合

この手順は、**確認済みのローカル書き出し一式を持っている場合のみ**、新規の空の Drupal サイトで使用します。公開リポジトリだけの場合、適用スクリプトはデータ不足を表示し、処理を停止します。

1. 導入先へテーマと、確認済みの `starter-kit/recipe/`・`manifest.yml` を配置します。
2. `manifest.yml` の `composer_packages` に示す拡張を用意します。検証済みの書き出し形式は Drupal 11.4.9／Canvas 1.12.0 が基準です。
3. `starter-kit/modules/` の補助モジュールをテーマと一緒に配置します。Recipe に必要なのは `clear_layers_canvas_taxonomy` と `clear_layers_starter_kit` です。`canvas_library_display` は初期構成ボタンで有効化する表示調整用です。Drupal がテーマ内から検出するため、`web/modules/custom/` へのコピーは不要です。同名モジュールがテーマ外にもある場合は、Drupal が検出しているパスと内容を確認してください。
4. 適用前の計画を確認します。

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/scripts/apply-starter-kit.php -- plan
```

`ready: true` を確認してから反映します。

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/scripts/apply-starter-kit.php -- apply
vendor/bin/drush cr
```

適用すると、必要な拡張のインストール、設定・ページ・メニュー・分類・画像の作成、既定テーマ・トップ／404ページ・タイムゾーン・公開コンテンツの閲覧権限の設定を行います。サイト名・メールアドレス・管理者アカウントは複製しません。既存コンテンツ・Canvas 下書き・設定競合・チェックサム不一致がある場合は停止します。

既存サイトへ上書きするためのツールではありません。途中の失敗を自動でロールバックする処理でもないため、作成直後の空のサイトで使ってください。導入後は本文・画像・URL・Canvas 編集・テーマ設定を確認し、導入先に合わせて編集します。

## 検証範囲

書き出し元で Recipe 解析、設定・コンテンツの依存関係、添付画像・UUID 参照、コンポーネントの旧版、チェックサム、既存設定・下書きの保持を検証しています。別の空のデータベースへのインストールと、実ブラウザーでの操作確認は未実施です。
