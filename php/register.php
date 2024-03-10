<?php
/**
 * px2-site-search
 */
namespace picklesFramework2\px2SiteSearch;

/**
 * register.php
 */
class register {

	/**
	 * plugin - before content
	 * @param object $px Picklesオブジェクト
	 * @param object $conf プラグイン設定オブジェクト
	 */
	public static function before_content( $px = null, $conf = null ){
		if( count(func_get_args()) <= 1 ){
			return __CLASS__.'::'.__FUNCTION__.'('.( is_array($px) ? json_encode($px) : '' ).')';
		}

		// PX=site-search を登録
		$px->pxcmd()->register('site_search', function($px) use ($conf){
			$pxcmd = $px->get_px_command();
			if( ($pxcmd[1] ?? null) == 'create_index' ){
				$createIndex = new createIndex\createIndex($px, $conf);
				$createIndex->execute();
				// echo $px->internal_sub_request('/?PX=publish.run');
				echo $px->internal_sub_request('/?PX=site_search._.integrate_index');
			}elseif( ($pxcmd[1] ?? null) == '_' ){
				if( ($pxcmd[2] ?? null) == 'integrate_index' ){
					$main = new main($px, $conf);
					$main->integrate_index();
				}
			}
			exit();
		});
	}

	/**
	 * plugin - processor
	 * @param object $px Picklesオブジェクト
	 * @param object $conf プラグイン設定オブジェクト
	 */
	public static function processor( $px = null, $conf = null ){
		if( count(func_get_args()) <= 1 ){
			return __CLASS__.'::'.__FUNCTION__.'('.( is_array($px) ? json_encode($px) : '' ).')';
		}

		$realpath_plugin_files = $px->realpath_plugin_private_cache();
		$px->fs()->mkdir($realpath_plugin_files.'contents/');

		$json = (object) array();
		$json->href = $px->req()->get_request_file_path();
		$json->page_info = $px->site()->get_current_page_info();
		$json->content = strip_tags($px->bowl()->get('main'));
		$px->fs()->save_file($realpath_plugin_files.'contents/'.urlencode($json->href).'.json', json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
	}

}
