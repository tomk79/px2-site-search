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
$px2_site_search_config = '__px2_site_search_config__';

$realpath_controot = $paprika->fs()->get_realpath( $paprika->env()->realpath_controot );
$realpath_public_base = $realpath_controot.'common/site_search_index/';

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

header('Content-type: text/json');
echo json_encode($result);

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
