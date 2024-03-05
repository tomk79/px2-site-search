<?php
chdir(__DIR__);

$tmp_path_autoload = __DIR__;
while(1){
    if( is_file( $tmp_path_autoload.'/vendor/autoload.php' ) ){
        require_once( $tmp_path_autoload.'/vendor/autoload.php' );
        break;
    }

    if( $tmp_path_autoload == dirname($tmp_path_autoload) ){
        break;
    }
    $tmp_path_autoload = dirname($tmp_path_autoload);
    continue;
}
unset($tmp_path_autoload);

$paprika = new \picklesFramework2\paprikaFramework\fw\paprika(json_decode('{"file_default_permission":"775","dir_default_permission":"775","filesystem_encoding":"UTF-8","session_name":"PXSID","session_expire":1800,"directory_index":["index.html"],"realpath_controot":"../","realpath_homedir":"../../paprika/","path_controot":"/","realpath_files":"./php_page_files/","realpath_files_cache":"../caches/c/basic/php_page_files/","href":"/basic/php_page.php","page_info":{"path":"/basic/php_page.php{*}","content":"/basic/php_page.php","id":":auto_page_id.5","title":"2. PHPで実行する","title_breadcrumb":"2. PHPで実行する","title_h1":"2. PHPで実行する","title_label":"2. PHPで実行する","title_full":"2. PHPで実行する | Paprika Framework for Pickles 2","logical_path":"/basic/index.html","list_flg":"1","layout":"","orderby":"","keywords":"","description":"","category_top_flg":"","role":"","proc_type":""},"parent":{"title":"基本","title_label":"基本","href":"/basic/"},"breadcrumb":[{"title":"HOME","title_label":"HOME","href":"/"},{"title":"基本","title_label":"基本","href":"/basic/"}],"bros":[{"title":"1. PHPによるAPIにAJAX通信する","title_label":"1. PHPによるAPIにAJAX通信する","href":"/basic/php_api-ajax.html"},{"title":"2. PHPで実行する","title_label":"2. PHPで実行する","href":"/basic/php_page.php"},{"title":"3. プレビュー環境で動的に実行する","title_label":"3. プレビュー環境で動的に実行する","href":"/basic/php_preview.html"}],"children":[]}'), false);

ob_start();

$execute_php_content = function($paprika){
?>
<?php
// -----------------------------------
// 1. テンプレート生成のリクエストに対する処理
// テンプレート生成時には `$paprika` は生成されず、
// 通常のHTMLコンテンツと同様に振る舞います。
// アプリケーションは、後でテンプレート中のコンテンツエリアのコードを置き換えるため、
// キーワード `{$main}` を出力しておきます。
if( !isset($paprika) ){
	return;
}

// -----------------------------------
// 2. 出力するHTMLコンテンツを生成
// あるいは、動的な処理を実装します。
$content = '';
ob_start(); ?>
<p>この方法は、コンテンツ自体を動的なPHPプログラムとして実装し、パブリッシュ後の環境でも同様に動作する仕組みです。</p>
<p>プレビュー環境では、動的に処理されたコンテンツを動的にテーマに包んで出力します。パブリッシュ後には、テーマを含んだテンプレートが別途出力され、これに動的な成果物をバインドして画面に出力するように振る舞います。</p>
<p>プログラマーは、コンテンツの処理の最初と最後に規定の処理を埋め込む必要がありますが、それ以外は直感的なPHPプログラムでウェブアプリケーションを実装できます。</p>
<p>グローバル空間に <code>$paprika</code> が自動的にロードされます。</p>

<p>次の例は、動的に環境変数を出力するサンプルです。</p>

<h2>realpath( '.' );</h2>
<p>コンテンツルートディレクトリ(=<code>.px_execute.php</code> が置かれているディレクトリ)がカレントディレクトリとして認識されます。従って、パブリッシュ前にはプレビューのディレクトリ下、パブリッシュ後では <code>dist</code> ディレクトリ下と、パスが変わります。</p>
<pre><code><?= realpath( '.' ); ?></code></pre>

<h2>$_SERVER['PATH_INFO']</h2>
<pre><code><?= htmlspecialchars( $_SERVER['PATH_INFO'] ?? '' ); ?></code></pre>

<h2>$_SERVER</h2>
<pre><code><?php var_dump( $_SERVER ); ?></code></pre>

<h2>Current page info</h2>
<p>パブリッシュ後のコードは <code>$px</code> にアクセスできません。 ページ情報にアクセスできるのはパブリッシュ前だけです。</p>
<?php if(isset($px) && $px->site()){ ?>
<pre><code><?php var_dump( $px->site()->get_current_page_info() ); ?></code></pre>
<?php }else{ ?>
<pre><code>$px が存在しません。</code></pre>
<?php } ?>

<h2>$paprika->env()</h2>
<?php if( isset($paprika) ){ ?>
<pre><code><?php var_dump( $paprika->env() ); ?></code></pre>
<?php }else{ ?>
<p><code>$paprika</code> is not set.</p>
<?php } ?>

<?php
$content .= ob_get_clean();

// -----------------------------------
// 3. テンプレートにバインド
// テンプレート生成時に埋め込んだキーワード `{$main}` を、
// 生成したコンテンツのHTMLコードに置き換えます。
// echo $content;
$paprika->bowl()->put($content);
?><?php
};
$execute_php_content($paprika);
$content = ob_get_clean();
if(strlen($content)){
    $paprika->bowl()->put($content);
}
echo $paprika->bowl()->bind_template();
exit;
?>
