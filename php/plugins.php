<?php
/**
 * px2-site-search
 */
namespace picklesFramework2\px2SiteSearch;

/**
 * plugins.php
 */
class plugins{

	/**
	 * Picklesオブジェクト
	 */
	private $px;

	/**
	 * px2dthelper main
	 */
	private $main;

	/**
	 * constructor
	 *
	 * @param object $px $pxオブジェクト
	 * @param object $main main.php のインスタンス
	 */
	public function __construct( $px, $main ){
		$this->px = $px;
		$this->main = $main;
	}

	/**
	 * プラグインオプションを得る
	 * @param  string $plugin_name Plugins Function Name
	 * @param  string $func Function division
	 * @return array Plugin Options
	 */
	public function get_plugin_options( $plugin_name, $func ){
		$rtn = array();
		if( !is_string($plugin_name) || !strlen($plugin_name) ){
			return false;
		}
		$plugin_name = preg_replace('/^\\\\+/', '', $plugin_name); // 先頭のバックスラッシュを削除
		$plugin_name = preg_replace('/\\\\+/', '\\', $plugin_name); // 重複するバックスラッシュを1つにまとめる

		if( !is_string($func) || !strlen($func) ){
			$func = null;
		}
		if( is_string($func) ){
			$func = preg_split('/(?:\-\>|\.|\/|\\\\)+/', $func);
		}

		$function_list = $this->get_plugin_list();

		foreach( $function_list as $fnc_info ){
			if( is_array($func) && !preg_match('/^'.preg_quote( implode('.',$func), '/' ).'(?:\..*)?$/', $fnc_info['funcs_div']) ){
				continue;
			}
			$preg_result = preg_match('/^(.*?)\s*(?:\((.*)\))?$/', $fnc_info['function'], $preg_match);
			if( !$preg_result ){
				continue;
			}
			$row = array();
			$row['funcs_div'] = $fnc_info['funcs_div'];
			$row['function'] = $preg_match[1];
			$row['options'] = json_decode( $preg_match[2] ?? 'null' );
			$row['function'] = preg_replace('/^\\\\+/', '', $row['function']); // 先頭のバックスラッシュを削除
			$row['function'] = preg_replace('/\\\\+/', '\\', $row['function']); // 重複するバックスラッシュを1つにまとめる
			if( $row['function'] == $plugin_name ){
				array_push($rtn, $row);
			}
		}
		return json_decode(json_encode($rtn));
	}

	/**
	 * プラグインの一覧を取得する
	 * @param object|array $target 検索対象 (省略時、 `$px->conf()->funcs` を対象とする)
	 * @param string $parent 親階層名
	 * @return array プラグインの一覧
	 */
	private function get_plugin_list( $target = null, $parent = null ){
		if( is_null($target) ){
			$target = @$this->px->conf()->funcs;
		}
		if( !$target ){return array();}

		// var_dump($parent);
		$rtn = array();
		foreach( $target as $key=>$val ){
			$current = $key;
			if( is_string( $parent ) ){
				$current = implode('.', array($parent, $key));
			}

			if( is_array($val) || is_object($val) ){
				$tmp_result = $this->get_plugin_list($val, $current);
				$rtn = array_merge($rtn, $tmp_result);
				continue;
			}

			$row = array();
			$row['funcs_div'] = $current;
			$row['function'] = $val;
			array_push($rtn, $row);
		}
		return $rtn;
	}

}
