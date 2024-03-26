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
