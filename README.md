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
        // 省略時: 'client'
        'engine_type' => 'client',

        // クライアント用アセットを書き出す先のディレクトリ
        // 省略時: '/common/site_search_index/'
        'path_client_assets_dir' => '/common/site_search_index/',

        // コンテンツエリアを抽出するセレクタ
        // 省略時: '.contents'
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
- `data-local-storage-key`: px2-site-search に専有を許可する localStorage のキー
- `data-allow-client-cache`: index.json をキャッシュするか？ (true: キャッシュする, false: キャッシュしない)
-->
<script src="<?= $px->href('/common/site_search_index/assets/px2-site-search.js') ?>"
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

- `engine_type` オプションを追加した。
- `data-path-controot` オプションを削除した。
- `data-allow-client-cache` オプションを省略できない不具合を修正。

### tomk79/px2-site-search v0.1.0 (2024年3月20日)

- Initial Release.


## ライセンス - License

MIT License


## 作者 - Author

- (C)Tomoya Koyanagi <tomk79@gmail.com>
- website: <https://www.pxt.jp/>
- Twitter: @tomk79 <https://twitter.com/tomk79/>
