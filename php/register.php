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
		$px->pxcmd()->register('site-search', function($px) use ($conf){
			$pxcmd = $px->get_px_command();
			if( ($pxcmd[1] ?? null) == 'index' ){
				echo $px->internal_sub_request('/?PX=publish.run');
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

		$realpath_plugin_files = $px->realpath_plugin_files();
		$px->fs()->mkdir($realpath_plugin_files.'contents/');
		$px->fs()->save_file($realpath_plugin_files.'contents/'.urlencode($px->req()->get_request_file_path()).'.txt', $px->bowl()->get('main'));
	}

}
