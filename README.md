# px2-site-search

[Pickles 2](https://pickles2.com/)に、サイト内検索機能を追加します。

## Setup - セットアップ手順

### [Pickles 2 プロジェクト](https://pickles2.com/) をセットアップ

### 1. `composer.json` に、パッケージ情報を追加

```bash
$ composer require tomk79/px2-site-search
```

### 2. `px-files/config.php` を開き、プラグインを設定

```php
$conf->funcs->before_content = array(
    // PX=site_search
    picklesFramework2\px2SiteSearch\register::before_content(array(
        // 検索エンジンの種類
        // - 'client' = ブラウザ上で静的に動作する検索インデックス
        // - 'paprika' = Paprikaフレームワークを用いてサーバー上で動作する検索インデックス
        // 省略時: 'client'
        'engine_type' => 'client',

        // クライアント用アセットを書き出す先のディレクトリ
        // 省略時: '/common/site_search_index/'
        'path_client_assets_dir' => '/common/site_search_index/',

        // 非公開データの書き出し先ディレクトリ
        // 省略時: '/_sys/site_search_index/'
        'path_private_data_dir' => '/_sys/site_search_index/',

        // インデックスから除外するパス
        // 複数のパス(完全一致)、または正規表現で定義します。
        // 省略時: 除外しない
        'paths_ignore' => array(
            '/perfect_match_ignored/ignored.html', // 完全一致 による設定例
            '/^\/ignored\/.*$/i', // 正規表現による設定例
        ),

        // コンテンツエリアを抽出するセレクタ
        // 省略時: 'body'
        'contents_area_selector' => '.contents',

        // コンテンツから除外する要素のセレクタ
        // 省略時: 除外しない
        'ignored_contents_selector' => array(
            '.contents-ignored',
        ),
    )),
);
```

### 3. コンテンツまたはテーマに、検索UIを追加する

```html
<!--
アセットをロードする
先頭の `/common/site_search_index/` の部分は、 `path_client_assets_dir` で設定したパスを参照するように書き換えてください。

オプション:
- `data-path-controot`: Pickles 2 の `$conf->path_controot` の設定値 (省略可)
- `data-lang`: Pickles 2 の `$px->lang()` の値 (省略可)
- `data-local-storage-key`: px2-site-search に専有を許可する localStorage のキー
- `data-allow-client-cache`: index.json をキャッシュするか？ (true: キャッシュする, false: キャッシュしない)
-->
<script src="<?= $px->href('/common/site_search_index/assets/px2-site-search.js') ?>"
    data-path-controot="<?= $px->conf()->path_controot ?>"
    data-lang="<?= $px->lang() ?>"
    data-local-storage-key="px2-site-search"
    data-allow-client-cache="true"></script>
<link rel="stylesheet" href="<?= $px->href('/common/site_search_index/assets/px2-site-search.css') ?>" />

<!--
検索UIをページに埋め込む場合
-->
<h2>検索</h2>
<div id="cont-search-result-block"></div>
<script>
	px2sitesearch.createSearchForm('#cont-search-result-block');
</script>

<!--
検索ボタンから検索ダイアログを開く場合
-->
<h2>検索ボタン</h2>
<p><button class="px2-btn px2-btn--primary cont-search-button">検索ダイアログを開く</button></p>
<script>
	$('.cont-search-button').on('click', function(){
		px2sitesearch.openSearchDialog();
	});
</script>
```

### 4. インデックスファイルを生成する

```bash
$ php ./src_px2/.px_execute.php "/?PX=site_search.create_index"
```


## 管理画面拡張

`config.php` に次のような設定を追加します。

```php
$conf->plugins->px2dt->custom_console_extensions = array(
    'px2-site-search' => array(
        'class_name' => 'picklesFramework2\px2SiteSearch\cce\main()',
    ),
);
```


## PXコマンド - PX Commands

### PX=site_search.create_index

インデックスファイルを生成する。


## 変更履歴 - Change Log

### tomk79/px2-site-search v0.1.1 (リリース日未定)

- サーバーサイドで検索を実行できるようになった。
- `engine_type` オプションを追加した。
- `paths_ignore` オプションを追加した。
- `contents_area_selector` オプションのデフォルト値を `body` に変更した。
- `path_private_data_dir` オプションを追加した。
- `data-path-controot` オプションを省略できるようになった。
- `data-lang` オプションを追加。
- `data-allow-client-cache` オプションを省略できない不具合を修正。
- `X-PXFW-RELATEDLINK` によって追加された新しいパスが、キュー配列の先頭に追加されるようになった。

### tomk79/px2-site-search v0.1.0 (2024年3月20日)

- Initial Release.


## ライセンス - License

MIT License


## 作者 - Author

- (C)Tomoya Koyanagi <tomk79@gmail.com>
- website: <https://www.pxt.jp/>
- Twitter: @tomk79 <https://twitter.com/tomk79/>
