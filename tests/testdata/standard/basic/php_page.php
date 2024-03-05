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
