# Clear Layers Canvas Taxonomy

タクソノミーの公開一覧（`entity.taxonomy_term.canonical`）に、Canvas のページテンプレート `taxonomy_listing` を割り当てる補助モジュールです。

- メニューの URL 別名とタグのリンクは従来のままです。
- 元の Views 一覧を `Page content` の位置に表示します。絞り込み、アクセス制御、ページャー、RSS は Views が処理します。
- Canvas の「分類・タグの一覧」テンプレートで、ヘッダー・本文周辺・フッターの配置を共通編集できます。
- 一覧のカード内容は既存のブログ「card」コンテンツテンプレートで編集します。件数、絞り込み、並び順は Views の設定です。
- `/home`、記事本文、管理画面、検索、RSS のレイアウト割り当てを変更しません。
- テンプレートを無効化・削除すると通常の Canvas テンプレート選択に戻ります。モジュールの無効化／アンインストールでも通常の選択に戻ります。

公開用 URL 例: `/drupal`、`/taxonomy/term/2`。
Canvas 編集 URL: `/canvas/editor/page_variant/taxonomy_listing`。

Core / Canvas 更新時には、表示バリアント選択イベント、ページテンプレート API、タクソノミールートと Views の描画を検証してください。Core / contrib の直接変更は不要です。
