# サイト共通レイアウトと本文の管理

[README に戻る](../README.md)

通常ページの外枠は Canvas のページテンプレート「サイト共通レイアウト」（`site_layout`）1つで管理します。

```text
サイト共通レイアウト［Site Default］
├ ヘッダー・ナビゲーション：このテンプレートで直接編集
├ Page content：URL に応じて生成された本文
│  ├ 記事・固定ページ：用途別の全文コンテンツテンプレート
│  ├ Canvas ページ：ページ自身の部品
│  ├ 検索結果：検索用 Views 等の一覧設定
│  └ 分類・タグ一覧：taxonomy_term の Views 設定
└ フッター：このテンプレートで直接編集
```

## 編集場所

- **共通部分**：`/canvas/editor/page_variant/site_layout`。部品を直接選択・編集して公開します。Canvas の「Templates → Page templates」からも開けます。`Page content` は1つ保持します。
- **記事・固定ページ**：Canvas の用途別「Content templates」でフィールドの参照や配置を編集します。全文テンプレートの Page template は「Site Default」にするか「サイト共通レイアウト」を指定します。カード用表示モードは一覧側から利用します。
- **検索・タグ一覧**：`/admin/structure/views` で対象の一覧を編集します。件数・条件・並び順・表示モード・ページャーはそのまま Views が処理します。
- **タグ一覧のパネル**：`templates/views/views-view--taxonomy-term--page-1.html.twig` が `clear_layers:taxonomy-listing` を利用します。見出しは Views が解決したタイトルです。View の機械名または表示 ID を変えた場合は Twig の候補名も対応させてください。ブロックや RSS は包みません。
- **ヘッダーを表示しない例外ページ**：例外専用のページテンプレートを作り、そのページだけで選択します。

## v1.1.5以前の旧バージョンを使用している場合

この文書の編集方法は、v1.2.0の構成を新規作成したサイト、またはv1.2.0へ移行済みのサイトが対象です。

Clear Layers の v1.1.5以前の旧バージョンで `site_header` / `site_footer` パターンや専用の外枠を使っている場合は、[v1.1.5以前からの更新手順](upgrade-from-v1.1.5.md)を先に実行してください。移行、整理前のバックアップ、不要テンプレートの整理、検証、バックアップ削除を順に説明しています。
