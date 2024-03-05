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
