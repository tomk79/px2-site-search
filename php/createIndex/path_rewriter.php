<?php
/**
 * px2-site-search
 */
namespace picklesFramework2\px2SiteSearch\createIndex;

/**
 * PX Commands "publish" path rewriter
 */
class path_rewriter{

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
	 * パス変換ロジックを正規化する
	 *
	 * @param mixed $callback パス変換ロジック
	 * @return callback 正規化されたパス変換ロジック
	 */
	public function normalize_callback($callback){
		if( is_callable($callback) ){
			// コールバック関数が設定された場合
			return $callback;
		}
		if( is_string($callback) && strpos(trim($callback), 'function') === 0 ){
			// function で始まる文字列が設定された場合
			return eval('return '.$this->conf->path_files.';');
		}
		if( is_null($callback) ){
			// コールバック関数が設定されなかった場合
			return null;
		}
		return $callback;
	}

	/**
	 * パスを変換する
	 * @param  string $path     変換前のパス
	 * @param  mixed  $callback コールバック関数 または 変換ルール文字列
	 * @return [type]           変換後のパス
	 */
	public function rewrite($path, $callback){
		if( is_null($callback) ){
			// コールバック関数が設定されなかった場合
			return $path;
		}elseif( is_callable($callback) ){
			// コールバック関数が設定された場合
			return call_user_func( $callback, $path );
		}elseif( is_string($callback) ){
			$path_rewrited = $callback;
			$data = array(
				'dirname'=>$this->px->fs()->normalize_path(dirname($path ?? '')),
				'filename'=>basename($this->px->fs()->trim_extension($path) ?? ''),
				'ext'=>strtolower($this->px->fs()->get_extension($path) ?? ''),
			);
			$path_rewrited = str_replace( '{$dirname}', $data['dirname'], $path_rewrited );
			$path_rewrited = str_replace( '{$filename}', $data['filename'], $path_rewrited );
			$path_rewrited = str_replace( '{$ext}', $data['ext'], $path_rewrited );
			return $path_rewrited;
		}
		return $path;
	}

}
