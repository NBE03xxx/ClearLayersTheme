# v1.1.5以前の旧バージョンからの更新

[README に戻る](../README.md#v115以前の旧バージョンから更新する場合)

**対象：Clear Layers の v1.1.5以前の旧バージョンで、Canvas の共通パターン参照や分類専用の外枠を使用している既存サイト。** v1.2.0を初めて適用する利用者は、[新規サイトの初期構成](canvas-setup.md)を使用してください。

ここでいう「旧構成」は、v1.1.5以前の旧バージョンで使用した `site_header` / `site_footer` パターン、`shared-site-section` 参照、`taxonomy_listing` 専用外枠、PageRegion 由来の `theme_clear_layers` を指します。別テーマからの移行は [別のガイド](migration.md)で扱います。

## 手順の全体像

1. 移行スクリプトを実行する。**設定変更前のバックアップは、このスクリプトが自動作成します。**
2. 不要テンプレートの整理前に、Drush で設定を追加バックアップする。
3. 不要なテーマテンプレートを整理する。
4. 検証する。
5. 検証と復旧用の保管期間を終えたら、バックアップを削除する。

独立した「バックアップスクリプト」は同梱していません。手順1では移行ツールの自動バックアップ、手順2では標準の `drush config:export` を使います。移行後だけのバックアップで変更前の状態を復元することはできないため、手順1の自動バックアップも保持してください。

## 事前準備

v1.2.0のテーマファイルを配置し、Clear Layers を既定テーマとして使用している状態で進めます。Canvas の未公開変更を公開または破棄し、作業中は編集を止めます。通常のサイト全体のバックアップも取得してください。以下の設定バックアップには、記事・画像・リビジョン等のサイト全体のデータは含まれません。

すべて Drupal プロジェクト直下の同じターミナルで実行します。`layout_backup_dir` は今回だけのバックアップ先を示す変数です。ターミナルを閉じる場合は表示されたパスを控えてください。

```sh
umask 077
layout_backup_dir="$(mktemp -d /tmp/clear-layers-layout.XXXXXX)"
printf '%s\n' "$layout_backup_dir"
```

バックアップ先は公開ディレクトリ・リポジトリ外に作成し、実サイトの設定を公開しないでください。`/tmp` は一時保存先です。長期保管が必要な場合は、作業後に管理下の非公開保存先へ移してください。

## 1. 移行スクリプトを実行する

まず読み取り専用の計画を確認します。

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/scripts/migrate-site-layout.php -- plan
```

対象と内容を確認し、適用します。

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/scripts/migrate-site-layout.php -- apply "${layout_backup_dir:?}/site-layout-before.json"
vendor/bin/drush cr
```

スクリプトは `site-layout-before.json` を**設定変更の前に作成**します。保存に失敗した場合は変更しません。既存のバックアップファイルは上書きしません。計画に変更がなければ、バックアップも新規作成しません。

| 対象 | 処理 |
| --- | --- |
| `site_layout` | 共通パターンの部品を、入力値・UUID・親子関係を保って直接配置する |
| `taxonomy_listing` | 標準構成で、ページ・設定からの明示参照がなければ削除する |
| `canvas.settings:default_page_variant` | 必要な場合に `site_layout` にする |
| 記事・ページ本文、メニュー、Views 設定 | 維持する |
| `site_header` / `site_footer` パターン | v1.1.5以前の旧バージョンの参照・履歴との互換用に保持する |

有効な `site_layout` がない場合、未公開変更・翻訳・分類用テンプレートの独自配置や設定・参照・重複 UUID・Canvas の検証エラーがある場合は停止します。v1.1.5以前でも構成が異なるサイトは、自動移行の対象に合うか確認し、必要な内容を個別に移してください。

保持したパターンは、移行後の共通レイアウトとは同期しません。以後は `/canvas/editor/page_variant/site_layout` でヘッダー・フッターを編集します。再実行しても移行済みレイアウトにパターンを再コピーしません。

## 2. 整理前の設定をバックアップする

次の標準 Drush コマンドで、移行後・不要テンプレート整理前の設定一式を別に保存します。

```sh
vendor/bin/drush config:export --destination="${layout_backup_dir:?}/config-before-cleanup"
```

成功したことと、保存先に設定ファイルが作成されたことを確認してから手順3へ進みます。このバックアップには、存在する場合の `canvas.page_variant.theme_clear_layers`、`canvas.page_region.clear_layers.*`、`canvas.settings` などが含まれます。手順1の変更前 JSON と、手順2の整理前設定は両方保持します。

`scripts/export-starter-kit.php` は別サイト向けの導入データの書き出し用です。今回のバックアップには上記の `config:export` を使用します。

## 3. 不要なテーマテンプレートを整理する

「Clear_layers theme」（`theme_clear_layers`）は、v1.1.5以前の旧バージョンで使用した PageRegion の配置を再現する自動生成テンプレートです。手順1の移行スクリプトは、このテンプレートの削除や旧リージョンの無効化を行いません。

1. `site_layout` が有効で、サイトの既定になっていることを確認します。
2. 現在のページ・コンテンツテンプレート・他の設定・翻訳・下書き・過去のページリビジョンから `theme_clear_layers` が参照されていないことを確認します。参照があれば、割り当てや履歴の扱いを解決するまで削除を保留します。
3. 「外観 → Clear Layers の設定」（`/admin/appearance/settings/clear_layers`）の「Drupal Canvas」で、**「Use Drupal Canvas for page templates in this theme.」をオフにして設定を保存**します。v1.1.5以前の旧バージョン由来の PageRegion を無効化し、保存済みの内容は保持します。`site_layout` を既定とする共通レイアウトは引き続き利用できます。
4. Canvas の「Templates → Page templates」で、「Clear_layers theme」の操作メニューから削除します。すでに存在しない場合は、この削除操作は不要です。
5. キャッシュを再構築します。

```sh
vendor/bin/drush cr
```

PageRegion が有効なままだと、テーマ設定の保存時に「Clear_layers theme」が再生成されます。上記のチェックはオフのままにします。

「サイト共通レイアウト」、用途別コンテンツテンプレート、必要な例外ページ用テンプレートは保持します。`site_header` / `site_footer` パターンも、v1.1.5以前の旧バージョンの参照・履歴用に残しているため、一緒に削除しないでください。補助モジュール `clear_layers_canvas_taxonomy` はフッターメニューブロックの初期値を引き続き担当します。

## 4. 検証する

```sh
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/canvas-setup.php
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/site-layout.php
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/theme-integrity.php
vendor/bin/drush php:script web/themes/custom/clear_layers/tests/canvas-health.php
```

`site-layout.php` は、移行済みで Canvas に未公開変更がないサイトで実行します。移行の再実行で変更が発生しないこと、編集プレビューの部品選択情報、代表ページとタグ一覧・RSS を検証します。検証中に保存済み設定・下書きが変わらないことも照合します。

ブラウザーでは次を確認してください。

- Home・記事・固定ページ・検索・タグ一覧でヘッダー・フッターが各1つ表示され、本文と一覧・絞り込み・ページャーが維持されている。
- 「サイト共通レイアウト」に Default の印があり、内部の部品を選択・編集・保存・公開できる。
- PC・スマートフォン幅、明暗モード、RSS が正常で、テーマ設定を保存しても不要テンプレートが再生成されない。

検証に失敗した場合はバックアップを保持し、復元する対象と範囲を確認してください。手順1の JSON は変更前の設定、手順2のエクスポートは整理直前の設定です。必要な設定を Drupal の設定・設定エンティティ API で復元し、移行後に加えた編集を上書きしないようにします。SQL による直接更新は行いません。

## 5. 検証後にバックアップを削除する

**手順4の検証がすべて完了し、復旧用の保管期間も終えてから実行します。** 問題が残る場合や、復旧に必要な間は保持してください。

次は手順1・2で今回作成したバックアップだけを削除する例です。ファイル・ディレクトリごとに確認して削除し、最後に空の作業ディレクトリを取り除きます。

```sh
printf '%s\n' "${layout_backup_dir:?}"
if [ -f "$layout_backup_dir/site-layout-before.json" ]; then
  rm -i -- "$layout_backup_dir/site-layout-before.json"
fi
rm -rI -- "${layout_backup_dir:?}/config-before-cleanup"
rmdir -- "${layout_backup_dir:?}"
unset layout_backup_dir
```

削除を中止してファイルが残った場合は `rmdir` が停止します。別の保存先に退避したコピーがある場合も、同じ保管方針に従って、今回の対象を確認してから削除してください。サイト全体の通常のバックアップや、v1.1.5以前の参照・履歴用パターンは、この削除対象には含めません。
