# px2-site-search

## Setup - セットアップ手順

### [Pickles 2 プロジェクト](https://pickles2.com/) をセットアップ

### 1. `composer.json` に、パッケージ情報を追加

```
$ composer require tomk79/px2-site-search
```

### 2. `px-files/config.php` を開き、プラグインを設定

```php
$conf->funcs->before_content = array(
    // PX=site_search
    picklesFramework2\px2SiteSearch\register::before_content(array(
        // クライアント用アセットを書き出す先のディレクトリ
        // 省略時: '/common/site_search_index/'
        'path_client_assets_dir' => '/common/site_search_index/',
    )),
);
```


## PXコマンド - PX Commands

### PX=site_search.create_index

インデックスファイルを生成する。


## 変更履歴 - Change Log

### tomk79/px2-site-search v0.1.0 (リリース日未定)

- Initial Release.


## ライセンス - License

MIT License


## 作者 - Author

- (C)Tomoya Koyanagi <tomk79@gmail.com>
- website: <https://www.pxt.jp/>
- Twitter: @tomk79 <https://twitter.com/tomk79/>
