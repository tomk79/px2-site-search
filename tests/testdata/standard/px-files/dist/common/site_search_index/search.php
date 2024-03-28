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

$paprika = new \picklesFramework2\paprikaFramework\fw\paprika(json_decode('{"file_default_permission":"775","dir_default_permission":"775","filesystem_encoding":"UTF-8","session_name":"PXSID","session_expire":1800,"directory_index":["index.html"],"realpath_controot":"../../","realpath_homedir":"../../../paprika/","path_controot":"/","realpath_files":"./search_files/","realpath_files_cache":"../../caches/c/common/site_search_index/search_files/","href":null,"page_info":null,"parent":null,"breadcrumb":null,"bros":null,"children":null}'), false);

ob_start();

$execute_php_content = function($paprika){
?>
<?php

$query = $paprika->req()->get_param('q');

$realpath_controot = $paprika->fs()->get_realpath( $paprika->env()->realpath_controot );
$realpath_public_base = $realpath_controot.'common/site_search_index/';

// --------------------------------------
// 検索する
$tnt = new \TeamTNT\TNTSearch\TNTSearch;
$tnt->loadConfig([
	'driver'    => 'sqlite',
	'database'  => $realpath_public_base.'tntsearch/articles.sqlite',
	'storage'   => $realpath_public_base.'tntsearch/',
	'stemmer'   => \TeamTNT\TNTSearch\Stemmer\PorterStemmer::class
]);
$tnt->selectIndex("index.sqlite");
$tnt->fuzziness(true);
$result = $tnt->search($query);

// --------------------------------------
// コンテンツリストと突き合わせ
$json = json_decode(file_get_contents(__DIR__.'/index.json'));

$rtn = (object) array(
	"result" => array(),
	"documentList" => (object) array(
		"contents" => array(),
	),
);

foreach($result['ids'] as $idx => $page_id){
	$page_info = $json->contents[$page_id];
	array_push($rtn->result, array(
		"id" => $idx,
	));
	array_push($rtn->documentList->contents, array(
		"h" => $page_info->h,
		"t" => $page_info->t,
		"h2" => $page_info->h2,
		"h3" => $page_info->h3,
		"h4" => $page_info->h4,
		"c" => $page_info->c,
	));
}


header('Content-type: text/json');
echo json_encode($rtn);

exit;
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
