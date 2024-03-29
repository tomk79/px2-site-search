<?php

$query = $paprika->req()->get_param('q');

$realpath_controot = $paprika->fs()->get_realpath( $paprika->env()->realpath_controot );
$realpath_public_base = $paprika->fs()->get_realpath( $realpath_controot.'/common/site_search_index/' );
$realpath_homedir = $paprika->fs()->get_realpath( $paprika->env()->realpath_homedir );
$realpath_private_data_base = $paprika->fs()->get_realpath( $realpath_homedir.'../'.'/_sys/site_search_index/' );

// --------------------------------------
// 検索する
$tnt = new \TeamTNT\TNTSearch\TNTSearch;
$tnt->loadConfig([
	'driver'    => 'sqlite',
	'database'  => $realpath_private_data_base.'tntsearch/articles.sqlite',
	'storage'   => $realpath_private_data_base.'tntsearch/',
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
