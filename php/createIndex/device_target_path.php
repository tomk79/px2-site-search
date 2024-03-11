<?php
/**
 * px2-site-search
 */
namespace picklesFramework2\px2SiteSearch\createIndex;

/**
 * PX Commands "publish" Device Target Path
 */
class device_target_path{

	/** Picklesオブジェクト */
	private $px;

	/** プラグイン設定 */
	private $plugin_conf;

	/**
	 * constructor
	 * @param object $px Picklesオブジェクト
	 * @param object $json プラグイン設定
	 */
	public function __construct( $px, $json ){
		$this->px = $px;
		$this->plugin_conf = $json;
	}

	/**
	 * パブリッシュ対象のパスか調べる
	 * @param  string $path 検査対象のパス
	 * @param  object $device_info デバイスの設定情報
	 * @return boolean 成否
	 */
	public function is_target_path( $path, $device_info ){

		$is_target = false;
		if( is_null( $device_info->paths_target ?? null ) ){
			// ターゲット指定がされていなければ、全体がターゲット
			$is_target = true;
		}elseif( is_array( $device_info->paths_target ) ){
			foreach( $device_info->paths_target as $path_target ){
				$preg_pattern = preg_quote( $path_target, '/' );
				if( preg_match('/'.preg_quote('\*','/').'/',$preg_pattern) ){
					// ワイルドカードが使用されている場合
					$preg_pattern = preg_replace('/'.preg_quote('\*','/').'/','(?:.*?)',$preg_pattern);//ワイルドカードをパターンに反映
					$preg_pattern = $preg_pattern.'$';//前方・後方一致
				}
				if( preg_match( '/^'.$preg_pattern.'/s' , $path ) ){
					$is_target = true;
					break;
				}
			}
		}
		if( !$is_target ){
			return false;
		}

		$is_ignore = false;
		if( is_null( $device_info->paths_ignore ?? null ) ){
			// 対象外指定がされていなければ、評価をスキップ
		}elseif( is_array( $device_info->paths_ignore ) ){
			foreach( $device_info->paths_ignore as $path_ignore ){
				$preg_pattern = preg_quote( $path_ignore, '/' );
				if( preg_match('/'.preg_quote('\*','/').'/',$preg_pattern) ){
					// ワイルドカードが使用されている場合
					$preg_pattern = preg_replace('/'.preg_quote('\*','/').'/','(?:.*?)',$preg_pattern);//ワイルドカードをパターンに反映
					$preg_pattern = $preg_pattern.'$';//前方・後方一致
				}
				if( preg_match( '/^'.$preg_pattern.'/s' , $path ) ){
					$is_ignore = true;
					break;
				}
			}
		}
		if( $is_ignore ){
			return false;
		}

		return true;
	}

}
