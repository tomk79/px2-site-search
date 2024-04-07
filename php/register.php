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
				$create_index = new main($px, $conf);
				$create_index->create_index();
			}
			exit();
		});
	}
}
