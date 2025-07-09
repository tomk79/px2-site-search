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

		// パブリッシュの前処理としてインデックス生成を実行する
		$pxcmd = $px->get_px_command();
		if( count($pxcmd) >= 2 && $pxcmd[0] == 'publish' && $pxcmd[1] == 'run' ){
			if(!$px->req()->get_param('path_region') && !$px->req()->get_param('paths_region') && !$px->req()->get_param('paths_ignore') && !$px->req()->get_param('keep_cache')){
				// オプションなし(=フルパブリッシュ)のときにのみ実行する
				$result = $px->internal_sub_request(
					'/?PX=site_search.create_index',
					array()
				);
				echo $result;
			}
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
