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

$paprika = new \picklesFramework2\paprikaFramework\fw\paprika(json_decode('{"file_default_permission":"775","dir_default_permission":"775","filesystem_encoding":"UTF-8","session_name":"PXSID","session_expire":1800,"directory_index":["index.html"],"realpath_controot":"../../../","realpath_homedir":"../../../../paprika/","path_controot":"/","realpath_files":"./sample_files/","realpath_files_cache":"../../../caches/c/basic/php_api-ajax_files/apis/sample_files/","href":null,"page_info":null,"parent":null,"breadcrumb":null,"bros":null,"children":null}'), false);

ob_start();

$execute_php_content = function($paprika){
?>
<?php
// AJAX API の実装サンプル
@header('Content-type: text/json');

$paprika->custom_function_a();

$obj = array();
$obj['_SERVER'] = $_SERVER;
$obj['paprika'] = $paprika;
$obj['paprikaConf'] = array(
    'undefined'=>$paprika->conf('undefined'),
    'sample1'=>$paprika->conf('sample1'),
    'sample2'=>$paprika->conf('sample2'),
    'sample3'=>$paprika->conf('sample3'),
    'prepend1'=>$paprika->conf('prepend1'),
    'prepend2'=>$paprika->conf('prepend2'),
    'custom_func_a'=>$paprika->conf('custom_func_a'),
    'dotEnvLoaded'=>$paprika->conf('extra')->dotenv_loaded,
);
$obj['realpath_current_dir'] = $paprika->fs()->get_realpath('./');
echo json_encode( $obj );
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
