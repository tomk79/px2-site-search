<?php
/**
 * px2-site-search
 */
namespace picklesFramework2\px2SiteSearch\create_index;
use picklesFramework2\px2SiteSearch\plugins;
use picklesFramework2\px2SiteSearch\main;

/**
 * PX Commands "site_search.create_index"
 */
class create_index {

	/** mainオブジェクト */
	private $main;

	/** Picklesオブジェクト */
	private $px;

	/** プラグイン設定 */
	private $plugin_conf;

	/** パブリッシュ設定 */
	private $publish_options;

	/** パス変換オブジェクト */
	private $path_rewriter;

	/** 一時パブリッシュディレクトリ管理オブジェクト */
	private $tmp_publish_dir;

	/** デバイス毎の対象パスを評価するオブジェクト */
	private $device_target_path;

	/** パス設定 */
	private $path_tmp_publish, $path_publish_dir, $path_controot;

	/** ドメイン設定 */
	private $domain;

	/** パブリッシュ範囲設定 */
	private $paths_region = array();

	/** パブリッシュ対象外範囲設定 */
	private $paths_ignore = array();

	/** キャッシュを消去しないフラグ */
	private $flg_keep_cache = false;

	/** ロックファイルの格納パス */
	private $path_lockfile;

	/** 処理待ちのパス一覧 */
	private $paths_queue = array();

	/** パスの状態一覧 */
	private $paths_status = array();

	/** 処理済みのパス数 */
	private $done_count = 0;

	/** Extension をマッチさせる正規表現 */
	private $preg_exts;

	/**
	 * constructor
	 * @param object $main mainオブジェクト
	 */
	public function __construct( $main ){
		$this->main = $main;
		$this->px = $main->px();
		$this->plugin_conf = $main->plugin_conf();

		// プラグイン設定の初期化
		// NOTE: ※ここで取り扱うのは、パブリッシュプラグインのオプション
		$plugins = new plugins($this->px, $this);
		$publish_options = $plugins->get_plugin_options('\tomk79\pickles2\publishEx\publish::register', 'before_content');
		if( !$publish_options ){
			$publish_options = $plugins->get_plugin_options('\picklesFramework2\commands\publish::register', 'before_content');
		}

		if( !is_object($publish_options) ){
			$publish_options = json_decode('{}');
		}
		if( !isset($publish_options->paths_ignore) || !is_array($publish_options->paths_ignore) ){
			$publish_options->paths_ignore = array();
		}
		if( !isset($publish_options->devices) || !is_array($publish_options->devices) ){
			$publish_options->devices = array();
		}
		foreach( $publish_options->devices as $device ){
			if( !is_object($device) ){
				$device = json_decode('{}');
			}
			if( !property_exists($device, 'user_agent') ){
				$device->user_agent = null;
			}
			if( !property_exists($device, 'path_publish_dir') ){
				$device->path_publish_dir = null;
			}
			if( !property_exists($device, 'path_rewrite_rule') ){
				$device->path_rewrite_rule = null;
			}
			if( !property_exists($device, 'paths_target') ){
				$device->paths_target = null;
			}
			if( !property_exists($device, 'paths_ignore') ){
				$device->paths_ignore = null;
			}
			if( !property_exists($device, 'rewrite_direction') ){
				$device->rewrite_direction = null;
			}
		}
		if( !property_exists($publish_options, 'skip_default_device') ){
			$publish_options->skip_default_device = false;
		}

		$this->publish_options = $publish_options;
		$this->path_rewriter = new path_rewriter( $this->px, $this->publish_options );
		$this->tmp_publish_dir = new tmp_publish_dir( $this->px, $this->publish_options );
		$this->device_target_path = new device_target_path( $this->px, $this->publish_options );

		$this->path_tmp_publish = $this->px->fs()->get_realpath( $this->px->get_realpath_homedir().'_sys/ram/publish/' );
		$this->path_lockfile = $this->path_tmp_publish.'applock.txt';
		if( $this->get_path_publish_dir() !== false ){
			$this->path_publish_dir = $this->get_path_publish_dir();
		}
		$this->domain = $this->px->conf()->domain;
		$this->path_controot = $this->px->conf()->path_controot;

		// Extensionをマッチさせる正規表現
		$process = array_keys( get_object_vars( $this->px->conf()->funcs->processor ) );
		foreach( $process as $key=>$val ){
			$process[$key] = preg_quote($val);
		}
		$this->preg_exts = '('.implode( '|', $process ).')';

		// パブリッシュ対象範囲
		$this->paths_region = array();
		$param_path_region = $this->px->req()->get_param('path_region');
		if( strlen($param_path_region ?? '') ){
			array_push( $this->paths_region, $param_path_region );
		}
		$param_paths_region = $this->px->req()->get_param('paths_region');
		if( is_array($param_paths_region ?? null) ){
			$this->paths_region = array_merge( $this->paths_region, $param_paths_region );
		}
		if( !count($this->paths_region) ){
			$path_region = $this->px->req()->get_request_file_path();
			$path_region = preg_replace('/^\\/*/is','/',$path_region);
			$path_region = preg_replace('/\/'.$this->px->get_directory_index_preg_pattern().'$/s','/',$path_region);
			array_push( $this->paths_region, $path_region );
		}

		$func_check_param_path = function($path){
			if( !preg_match('/^\//', $path) ){
				return false;
			}
			$path = preg_replace('/(?:\/|\\\\)/', '/', $path);
			if( preg_match('/(?:^|\/)\.{1,2}(?:$|\/)/', $path) ){
				return false;
			}
			return true;
		};
		foreach( $this->paths_region as $tmp_key => $tmp_localpath_region ){
			if( !$func_check_param_path( $tmp_localpath_region ) ){
				unset($this->paths_region[$tmp_key]);
				continue;
			}
		}

		foreach( $this->paths_region as $tmp_key => $tmp_localpath_region ){
			// 2重拡張子の2つ目を削除
			if( !is_dir('./'.$tmp_localpath_region) && preg_match( '/\.'.$this->preg_exts.'\.'.$this->preg_exts.'$/is', $tmp_localpath_region ) ){
				$this->paths_region[$tmp_key] = preg_replace( '/\.'.$this->preg_exts.'$/is', '', $tmp_localpath_region );
			}
			// 先頭がスラッシュじゃない場合は追加する
			$this->paths_region[$tmp_key] = preg_replace( '/^\\/*/is', '/', $this->paths_region[$tmp_key] );
		}
		unset(
			$path_region,
			$param_path_region,
			$param_paths_region,
			$func_check_param_path,
			$tmp_localpath_region,
			$tmp_key );

		// キャッシュを消去しないフラグ
		$this->flg_keep_cache = !!$this->px->req()->get_param('keep_cache');

		// パブリッシュ対象外の範囲
		$this->paths_ignore = $this->px->req()->get_param('paths_ignore');
		if( is_string($this->paths_ignore) ){
			$this->paths_ignore = array( $this->paths_ignore );
		}
		if( !is_array($this->paths_ignore) ){
			$this->paths_ignore = array();
		}
		foreach( $this->paths_ignore as $tmp_key => $tmp_localpath_region ){
			// 先頭がスラッシュじゃない場合は追加する
			$this->paths_ignore[$tmp_key] = preg_replace( '/^\\/*/is', '/', $this->paths_ignore[$tmp_key] );
		}
	}

	/**
	 * print CLI header
	 */
	private function cli_header(){
		ob_start();
		print $this->px->pxcmd()->get_cli_header();
		print 'publish directory(tmp): '.$this->path_tmp_publish."\n";
		print 'lockfile: '.$this->path_lockfile."\n";
		print 'domain: '.$this->domain."\n";
		print 'docroot directory: '.$this->path_controot."\n";
		print 'ignore: '.join(', ', $this->publish_options->paths_ignore)."\n";
		print 'region: '.join(', ', $this->paths_region)."\n";
		print 'ignore (tmp): '.join(', ', $this->paths_ignore)."\n";
		print 'keep cache: '.($this->flg_keep_cache ? 'true' : 'false')."\n";
		print 'devices:'."\n";
		foreach($this->publish_options->devices as $key=>$device){
			print '  - device['.$key.']:'."\n";
			print '    - user_agent: '.$device->user_agent."\n";
			print '    - path_rewrite_rule: '.$device->path_rewrite_rule."\n";
			print '    - paths_target: '.(is_array($device->paths_target) ? join(', ', $device->paths_target) : '')."\n";
			print '    - paths_ignore: '.(is_array($device->paths_ignore) ? join(', ', $device->paths_ignore) : '')."\n";
			print '    - rewrite_direction: '.$device->rewrite_direction."\n";
		}
		print 'skip default device: '.($this->publish_options->skip_default_device ? 'true' : 'false')."\n";
		print '------------'."\n";
		flush();
		return ob_get_clean();
	}

	/**
	 * print CLI footer
	 */
	private function cli_footer(){
		ob_start();
		print $this->px->pxcmd()->get_cli_footer();
		return ob_get_clean();
	}

	/**
	 * report
	 */
	private function cli_report(){
		$cnt_queue = count( $this->paths_queue );
		$cnt_done = $this->done_count;
		ob_start();
		print $cnt_done.'/'.($cnt_queue+$cnt_done)."\n";
		print 'queue: '.$cnt_queue.' / done: '.$cnt_done."\n";
		return ob_get_clean();
	}

	/**
	 * execute
	 */
	public function execute(){
		$px = $this->px;
		header('Content-type: text/plain;');
		$total_time = time();
		print $this->cli_header();

		$validate = $this->validate();
		if( !$validate['status'] ){
			print $validate['message']."\n";
			print $this->cli_footer();
			exit;
		}
		flush();
		if( !$this->lock() ){//ロック
			print '------'."\n";
			print 'publish is now locked.'."\n";
			print '  (lockfile updated: '.@date('c', filemtime($this->path_lockfile)).')'."\n";
			print 'Try again later...'."\n";
			print 'exit.'."\n";
			print $this->cli_footer();
			exit;
		}
		print "\n";
		print "\n";

		print '============'."\n";
		print '## Clearing caches'."\n";
		print "\n";
		$this->clearcache();
		print "\n";

		// make instance $site
		$this->px->set_site( new \picklesFramework2\site($this->px) );

		print '============'."\n";
		print '## Making list'."\n";
		print "\n";
		print '-- making list by Sitemap'."\n";
		$this->make_list_by_sitemap();
		print "\n";
		print '-- making list by Directory Scan'."\n";
		foreach( $this->get_region_root_path() as $path_region ){
			$this->make_list_by_dir_scan( $path_region );
		}
		$this->paths_queue = array_reverse($this->paths_queue);
		print "\n";
		print '============'."\n";
		print '## Start publishing'."\n";
		print "\n";
		print $this->cli_report();
		print "\n";

		file_put_contents($this->path_tmp_publish.'timelog.txt', 'Started at: '.date('c', $total_time)."\n", FILE_APPEND); // 2020-04-01 @tomk79 記録するようにした。

		$device_list = $this->publish_options->devices;
		foreach($device_list as $device_num => $device_info){
			$device_list[$device_num]->user_agent = trim($device_info->user_agent).'/PicklesCrawler';
			if($this->px->fs()->is_dir($device_info->path_publish_dir)){
				$device_list[$device_num]->path_publish_dir = $this->px->fs()->get_realpath( $device_info->path_publish_dir );
			}else{
				$device_list[$device_num]->path_publish_dir = false;
			}
			$device_list[$device_num]->path_rewrite_rule = $this->path_rewriter->normalize_callback( $device_list[$device_num]->path_rewrite_rule ?? null );
		}
		if( !$this->publish_options->skip_default_device ){
			// 標準デバイスを暗黙的に追加する
			array_unshift($device_list, json_decode(json_encode(array(
				'user_agent' => '',
				'path_publish_dir' => $this->path_publish_dir,
				'path_rewrite_rule' => $this->path_rewriter->normalize_callback(null),
				'paths_target'=>null,
				'paths_ignore'=>null,
				'rewrite_direction'=>null,
			))));
		}

		while(1){
			set_time_limit(5*60);
			flush();
			if( !count( $this->paths_queue ) ){
				break;
			}
			$path = array_shift($this->paths_queue);

			print '------------'."\n";
			print $path."\n";

			foreach($device_list as $device_num => $device_info){
				$htdocs_sufix = $this->tmp_publish_dir->get_sufix( $device_info->path_publish_dir );
				if(!$htdocs_sufix){ $htdocs_sufix = '';}
				$path_rewrited = $this->path_rewriter->rewrite($path, $device_info->path_rewrite_rule);
				$is_device_target_path = $this->device_target_path->is_target_path( $path, $device_info );

				if( !$is_device_target_path ){
					// デバイス設定で対象外と判定された場合、スキップ
					print ' -> Skipped.'."\n";
					continue;
				}

				$path_type = $this->px->get_path_type( $path );
				$path_ext = strtolower( preg_replace('/^.*?([a-zA-Z0-9\_\-]+)$/', '$1', $path_rewrited??'') );
				if( $this->is_ignored_path($path) ){
					// 除外されたパスはスキップ
					print ' -> Ignored path.'."\n";

				}elseif( !preg_match('/^(?:html?|php)$/', $path_ext) ){
					// HTMLドキュメント以外はスキップ
					print ' -> Non HTML file.'."\n";

				}elseif( $path_type != 'normal' && $path_type !== false ){
					// 物理ファイルではないものはスキップ
					print ' -> Non file URL.'."\n";
	
				}elseif( $this->px->fs()->is_dir(dirname($_SERVER['SCRIPT_FILENAME']).$path) ){
					// ディレクトリを処理
					$this->px->fs()->mkdir( $this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot.$path_rewrited );
					print ' -> A directory.'."\n";

				}else{
					// ファイルを処理
					$ext = strtolower( pathinfo( $path , PATHINFO_EXTENSION ) );
					$proc_type = $this->px->get_path_proc_type( $path );
					$status_code = null;
					$status_message = null;
					$errors = array();
					$microtime = microtime(true);
					switch( $proc_type ){
						case 'pass':
							// pass
							print $ext.' -> '.$proc_type."\n";

							$json = (object) array();
							$json->href = $path_rewrited;
							$json->page_info = $this->px->internal_sub_request(
								$path.'?PX=api.get.page_info',
								array('output'=>'json'),
								$return_var);
							// $json->page_info = $this->px->site()->get_page_info($path_rewrited);
							$json->content = $this->px->fs()->read_file(dirname($_SERVER['SCRIPT_FILENAME']).$path);
							$this->save_content_json($json);

							$status_code = 200;
							break;

						case 'direct':
						default:
							// pickles execute
							print $ext.' -> '.$proc_type."\n";

							if( !isset( $device_info->params ) ){
								$device_info->params = null;
							}

							$bin = $this->px->internal_sub_request(
								$this->merge_params($path, $device_info->params),
								array(
									'output'=>'json',
									'user_agent'=>$device_info->user_agent,
								),
								$return_var);
							if( !is_object($bin) ){
								$bin = new \stdClass;
								$bin->status = 500;
								$tmp_err_msg = 'Unknown server error';
								$tmp_err_msg .= "\n".'PHP returned status code "'.$return_var.'" on exit. There is a possibility of "Parse Error" or "Fatal Error" was occured.';
								$tmp_err_msg .= "\n".'Hint: Normally, "Pickles 2" content files are parsed as PHP scripts. If you are using "<'.'?", "<'.'?php", "<'.'%", or "<'.'?=" unintentionally in contents, might be the cause.';
								$bin->message = $tmp_err_msg;
								$bin->errors = array();
								// $bin->errors = array($tmp_err_msg);
								$bin->relatedlinks = array();
								$bin->body_base64 = base64_encode('');
								// $bin->body_base64 = base64_encode($tmp_err_msg);
								unset($tmp_err_msg);
							}
							$bin->status = $bin->status ?? 200;
							$status_code = $bin->status ?? null;
							$status_message = $bin->message ?? null;
							$errors = $bin->errors ?? null;
							if( $bin->status >= 500 ){
								$this->alert_log(array( @date('c'), $path, 'status: '.$bin->status.' '.$bin->message ));
							}elseif( $bin->status >= 400 ){
								$this->alert_log(array( @date('c'), $path, 'status: '.$bin->status.' '.$bin->message ));
							}elseif( $bin->status >= 300 ){
								$this->alert_log(array( @date('c'), $path, 'status: '.$bin->status.' '.$bin->message ));
							}elseif( $bin->status >= 200 ){
								// 200 番台は正常
							}elseif( $bin->status >= 100 ){
								$this->alert_log(array( @date('c'), $path, 'status: '.$bin->status.' '.$bin->message ));
							}else{
								$this->alert_log(array( @date('c'), $path, 'Unknown status code.' ));
							}

							// コンテンツの書き出し処理
							// エラーが含まれている場合でも、得られたコンテンツを出力する。
							$json = (object) array();
							$json->href = $path_rewrited;
							$json->page_info = $this->px->internal_sub_request(
								$path.'?PX=api.get.page_info',
								array('output'=>'json'),
								$return_var);
							// $json->page_info = $this->px->site()->get_page_info($path_rewrited);
							$json->content = base64_decode( $bin->body_base64 ?? null );
							$this->save_content_json($json);

							$bin->relatedlinks = array_reverse($bin->relatedlinks);
							foreach( $bin->relatedlinks as $link ){
								$link = $this->px->fs()->get_realpath( $link, dirname($this->path_controot.$path).'/' );
								$link = $this->px->fs()->normalize_path( $link );
								$tmp_link = preg_replace( '/^'.preg_quote($this->px->get_path_controot(), '/').'/s', '/', ''.$link );
								if( $this->px->fs()->is_dir( $this->px->get_realpath_docroot().'/'.$link ) ){
									$this->make_list_by_dir_scan( $tmp_link.'/' );
								}else{
									$this->add_queue( $tmp_link );
								}
							}

							// エラーメッセージを alert_log に追記
							if( is_array( $bin->errors ) && count( $bin->errors ) ){
								foreach( $bin->errors as $tmp_error_row ){
									$this->alert_log(array( @date('c'), $path, $tmp_error_row ));
								}
							}

							break;
					}

					$str_errors = '';
					if( is_array($errors) && count($errors) ){
						$str_errors .= count($errors).' errors: ';
						$str_errors .= implode(', ', $errors).';';
					}
					$this->log(array(
						@date('c') ,
						$path ,
						$proc_type ,
						$status_code ,
						$status_message ,
						$str_errors,
						(file_exists($this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot.$path_rewrited) ? filesize($this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot.$path_rewrited) : false),
						$device_info->user_agent,
						microtime(true)-$microtime
					));
				}

			}

			$this->paths_status[$path] = true;
			$this->done_count ++;
			print $this->cli_report();

			$this->touch_lockfile();

			if( !count( $this->paths_queue ) ){
				break;
			}
		}

		print '============'."\n";
		print '## Create index file.'."\n";
		$this->main->integrate_index();

		print "\n";

		print '============'."\n";
		print '## done.'."\n";
		print "\n";

		$path_logfile = $this->path_tmp_publish.'alert_log.csv';
		clearstatcache();
		if( $this->px->fs()->is_file( $path_logfile ) ){
			sleep(1);
			$alert_log = $this->px->fs()->read_csv( $path_logfile );
			array_shift( $alert_log );
			$alert_total_count = count($alert_log);
			$max_preview_count = 20;
			$alert_header = '************************* '.$alert_total_count.' ALERTS ******';
			print $alert_header."\n";
			print "\n";
			$counter = 0;
			foreach( $alert_log as $key=>$row ){
				$counter ++;
				$tmp_number = '  ['.($key+1).'] ';
				print $tmp_number;
				print preg_replace('/(\r\n|\r|\n)/s', '$1'.str_pad('', strlen($tmp_number ?? ""), ' '), $row[2])."\n";
				print str_pad('', strlen($tmp_number ?? ""), ' ').'  in '.$row[1]."\n";
				if( $counter >= $max_preview_count ){ break; }
			}
			if( $alert_total_count > $max_preview_count ){
				print '  [etc...]'."\n";
			}
			print "\n";
			print '    You got total '.$alert_total_count.' alerts.'."\n";
			print '    see more: '.realpath($path_logfile)."\n";
			print str_pad('', strlen($alert_header ?? ""), '*')."\n";
			print "\n";
		}

		$end_time = time();
		print 'Total Time: '.($end_time - $total_time).' sec.'."\n";
		file_put_contents($this->path_tmp_publish.'timelog.txt', 'Ended at: '.date('c', $end_time)."\n", FILE_APPEND); // 2020-04-01 @tomk79 記録するようにした。
		file_put_contents($this->path_tmp_publish.'timelog.txt', 'Total Time: '.($end_time - $total_time).' sec'."\n", FILE_APPEND); // 2020-04-01 @tomk79 記録するようにした。
		print "\n";

		$this->unlock();

		print $this->cli_footer();
		exit;
	}


	/**
	 * パブリッシュログ
	 * @param array $row ログデータ
	 * @return bool ログ書き込みの成否
	 */
	private function log( $row ){
		$path_logfile = $this->path_tmp_publish.'publish_log.csv';
		if( !is_file( $path_logfile ) ){
			error_log( $this->px->fs()->mk_csv( array(array(
				'datetime' ,
				'path' ,
				'proc_type' ,
				'status_code' ,
				'status_message' ,
				'errors' ,
				'filesize',
				'user_agent',
				'proc_microtime'
			)) ), 3, $path_logfile );
			clearstatcache();
		}
		return error_log( $this->px->fs()->mk_csv( array($row) ), 3, $path_logfile );
	}

	/**
	 * パブリッシュアラートログ
	 * @param array $row ログデータ
	 * @return bool ログ書き込みの成否
	 */
	private function alert_log( $row ){
		$path_logfile = $this->path_tmp_publish.'alert_log.csv';
		if( !is_file( $path_logfile ) ){
			error_log( $this->px->fs()->mk_csv( array(array(
				'datetime' ,
				'path' ,
				'error_message'
			)) ), 3, $path_logfile );
			clearstatcache();
		}
		return error_log( $this->px->fs()->mk_csv( array($row) ), 3, $path_logfile );
	}

	/**
	 * validate
	 */
	private function validate(){
		$rtn = array('status'=>true, 'message'=>'');
		return $rtn;
	}

	/**
	 * clearcache
	 */
	private function clearcache(){

		// キャッシュを消去
		if( !$this->flg_keep_cache ){
			(new \picklesFramework2\commands\clearcache( $this->px ))->exec();
		}

		return true;
	}

	/**
	 * make list by sitemap
	 *
	 * @return bool 常に `true` を返します。
	 */
	private function make_list_by_sitemap(){
		$sitemap = $this->px->site()->get_sitemap();
		foreach( $sitemap as $page_info ){
			set_time_limit(30);
			$href = $this->px->href( $page_info['path'] );
			if( preg_match('/^(?:[a-zA-Z0-9]+\:)?\/\//', $href) ){
				// プロトコル名、またはドメイン名から始まるリンク先はスキップ
				continue;
			}
			$href = preg_replace( '/\/$/s', '/'.$this->px->get_directory_index_primary(), $href );
			$href = preg_replace( '/^'.preg_quote($this->px->get_path_controot(), '/').'/s', '/', $href );
			$this->add_queue( $href );
		}
		return true;
	}

	/**
	 * make list by directory scan
	 *
	 * @param string $path ファイル または ディレクトリ のパス
	 * @return bool 常に真
	 */
	private function make_list_by_dir_scan( $path = null ){

		$realpath = $this->px->fs()->get_realpath('./'.$path);

		if( !file_exists( $realpath ) ){
			// 直にファイルが存在しない場合、2重拡張子のファイルを検索
			$tmp_process = array_keys( get_object_vars( $this->px->conf()->funcs->processor ) );
			foreach( $tmp_process as $tmp_ext ){
				if( $this->px->fs()->is_file( $realpath.'.'.$tmp_ext ) ){
					$realpath = $realpath.'.'.$tmp_ext;
					break;
				}
			}
			unset($tmp_process, $tmp_ext);
		}

		if( $this->px->fs()->is_file( $realpath ) ){
			$tmp_localpath = $this->px->fs()->get_realpath('/'.$path);
			if( preg_match( '/\.'.$this->preg_exts.'\.'.$this->preg_exts.'$/is', $tmp_localpath ) ){
				$tmp_localpath = preg_replace( '/\.'.$this->preg_exts.'$/is', '', $tmp_localpath );
			}
			if( $this->px->get_path_proc_type( $tmp_localpath ) == 'ignore' || $this->px->get_path_proc_type( $tmp_localpath ) == 'pass' ){
				$tmp_localpath = $this->px->fs()->get_realpath('/'.$path);
			}
			$tmp_localpath = $this->px->fs()->normalize_path( $tmp_localpath );
			$this->add_queue( $tmp_localpath );
			return true;
		}

		$ls = $this->px->fs()->ls( $realpath );
		if( !is_array($ls) ){
			$ls = array();
		}
		// ↓ `/index.html` がignoreされている場合に、
		// 　ディレクトリスキャンがキャンセルされてしまう問題があり、
		// 　ここでの評価はしないことにした。
		// 　※add_queue()で評価しているので、結果問題なし。
		// if( $this->px->is_ignore_path( './'.$path ) ){
		// 	return true;
		// }


		foreach( $this->px->conf()->paths_proc_type as $row => $type ){
			// $conf->paths_proc_type の設定から、
			// 明らかに除外できると判断できるディレクトリは再帰処理をキャンセルする。
			// 設定値の末尾が `/*` で終わっている ignore 指定の行は、 "ディレクトリ以下すべて除外" と断定し、
			// これにマッチしたディレクトリをキャンセルの対象とする。
			if( !is_string($row) ){
				continue;
			}
			if( $type != 'ignore' ){
				continue;
			}
			if( strrpos($row, '/*') !== strlen($row)-2 ){
				continue;
			}
			$preg_pattern = preg_quote($this->px->fs()->normalize_path($this->px->fs()->get_realpath($row)), '/');
			$realpath_controot = $this->px->fs()->normalize_path( $this->px->fs()->get_realpath( $this->px->get_path_docroot().$this->px->get_path_controot() ) );
			if( preg_match('/\*/',$preg_pattern) ){
				// ワイルドカードが使用されている場合
				$preg_pattern = preg_quote($row,'/');
				$preg_pattern = preg_replace('/'.preg_quote('\*','/').'/','(?:.*?)',$preg_pattern);//ワイルドカードをパターンに反映
			}elseif(is_dir($realpath_controot.$row)){
				$preg_pattern = preg_quote($this->px->fs()->normalize_path($this->px->fs()->get_realpath($row)).'/','/');
			}elseif(is_file($realpath_controot.$row)){
				$preg_pattern = preg_quote($this->px->fs()->normalize_path($this->px->fs()->get_realpath($row)),'/');
			}
			$path_child = $this->px->fs()->normalize_path( $this->px->fs()->get_realpath( $path ).'/' );
			if( preg_match( '/^'.$preg_pattern.'$/s' , $path_child ) ){
				return true;
			}
		}

		foreach( $ls as $basename ){
			set_time_limit(30);
			$this->make_list_by_dir_scan( $path.DIRECTORY_SEPARATOR.$basename );
		}
		return true;
	}

	/**
	 * add queue
	 * @param string $path 対象のパス
	 * @return bool 真偽
	 */
	private function add_queue( $path ){
		$path_type = $this->px->get_path_type( $path );
		if($path_type != 'normal'){
			// `normal` ではないもの(`data`, `javascript`, `anchor`, `full_url` など)は、
			// 物理ファイルを出力するものではないので、キューに送らない。
			return false;
		}

		$path = $this->px->fs()->normalize_path( $this->px->fs()->get_realpath( $path, $this->path_controot ) );
		$path = preg_replace('/\#.*$/', '', $path);
		$path = preg_replace('/\?.*$/', '', $path);
		if( preg_match( '/\/$/', $path ) ){
			$path .= $this->px->get_directory_index_primary();
		}

		if( $this->px->is_ignore_path( $path ) || $this->is_ignore_path( $path ) || !$this->is_region_path( $path ) ){
			// 対象外, パブリッシュ対象外, 範囲外
			// 対象外パスの親ディレクトリが対象パスの場合は、ディレクトリ単体でキューに登録を試みる。
			// 　　ディレクトリの内容がすべて一時対象外に指定された場合に、
			// 　　一時パブリッシュディレクトリにフォルダが作られないため、
			// 　　同期時にディレクトリごと削除されてしまうことを防止するため。
			$dirname = $this->px->fs()->normalize_path(dirname($path));
			if($dirname != '/'){ $this->add_queue( $dirname ); }
			return false;
		}
		if( array_key_exists($path, $this->paths_status) ){
			// 登録済み
			return false;
		}
		array_unshift($this->paths_queue, $path);
		$this->paths_status[$path] = false;
		print 'added queue - "'.$path.'"'."\n";
		return true;
	}

	/**
	 * パブリッシュ対象か調べる
	 * @param string $path 対象のパス
	 * @return bool 真偽
	 */
	private function is_ignore_path( $path ){
		static $rtn = array();
		if( is_null($path) ){
			return true;
		}
		$path = $this->px->fs()->get_realpath( '/'.$path );
		if( is_dir('./'.$path) ){
			$path .= '/'.$this->px->get_directory_index_primary();
		}
		if( preg_match('/(?:\/|\\\\)$/', $path) ){
			$path .= $this->px->get_directory_index_primary();
		}
		$path = $this->px->fs()->normalize_path($path);

		if( is_bool( $rtn[$path] ?? null ) ){
			return $rtn[$path];
		}

		foreach( $this->publish_options->paths_ignore as $row ){
			if(!is_string($row)){continue;}
			$preg_pattern = preg_quote($this->px->fs()->normalize_path($this->px->fs()->get_realpath($row)), '/');
			if( preg_match('/\*/',$preg_pattern) ){
				// ワイルドカードが使用されている場合
				$preg_pattern = preg_quote($row,'/');
				$preg_pattern = preg_replace('/'.preg_quote('\*','/').'/','(?:.*?)',$preg_pattern);//ワイルドカードをパターンに反映
			}elseif(is_dir($row)){
				$preg_pattern = preg_quote($this->px->fs()->normalize_path($this->px->fs()->get_realpath($row)).'/','/');
			}elseif(is_file($row)){
				$preg_pattern = preg_quote($this->px->fs()->normalize_path($this->px->fs()->get_realpath($row)),'/');
			}
			if( preg_match( '/^'.$preg_pattern.'$/s' , $path ) ){
				$rtn[$path] = true;
				return $rtn[$path];
			}
		}
		foreach( $this->paths_ignore as $path_ignore ){
			$preg_pattern = preg_quote( $path_ignore, '/' );
			if( preg_match('/'.preg_quote('\*','/').'/',$preg_pattern) ){
				// ワイルドカードが使用されている場合
				$preg_pattern = preg_replace('/'.preg_quote('\*','/').'/','(?:.*?)',$preg_pattern);//ワイルドカードをパターンに反映
				$preg_pattern = $preg_pattern.'$';//前方・後方一致
			}
			if( preg_match( '/^'.$preg_pattern.'/s' , $path ) ){
				$rtn[$path] = true;
				return $rtn[$path];
			}
		}
		$rtn[$path] = false;// <- default
		return $rtn[$path];
	}

	/**
	 * パブリッシュ範囲内か調べる
	 * @param string $path 対象のパス
	 * @return bool 真偽
	 */
	private function is_region_path( $path ){
		$path = $this->px->fs()->get_realpath( '/'.$path );
		if( $this->px->fs()->is_dir('./'.$path) ){
			$path .= '/';
		}
		$path = $this->px->fs()->normalize_path($path);
		$is_region = false;
		foreach( $this->paths_region as $path_region ){
			if( preg_match( '/^'.preg_quote( $path_region, '/' ).'/s' , $path ) ){
				$is_region = true;
				break;
			}
		}
		if( !$is_region ){
			return false;
		}
		foreach( $this->paths_ignore as $path_ignore ){
			$preg_pattern = preg_quote( $path_ignore, '/' );
			if( preg_match('/'.preg_quote('\*','/').'/',$preg_pattern) ){
				// ワイルドカードが使用されている場合
				$preg_pattern = preg_replace('/'.preg_quote('\*','/').'/','(?:.*?)',$preg_pattern);//ワイルドカードをパターンに反映
				$preg_pattern = $preg_pattern.'$';//前方・後方一致
			}
			if( preg_match( '/^'.$preg_pattern.'/s' , $path ) ){
				return false;
			}
		}
		return true;
	}


	/**
	 * パブリッシュ範囲のルートパスを得る
	 * @return string パブリッシュ範囲のルートパス
	 */
	private function get_region_root_path(){
		$rtn = array();
		foreach( $this->paths_region as $path_region ){
			$path = $this->px->fs()->get_realpath( '/'.$path_region );
			$path = $this->px->fs()->normalize_path($path);
			// ↓スキャンする対象が実在するディレクトリである必要はないので削除。
			// 　実在しない場合は無視されるだけなので問題ない。
			// 　この処理が有効だった場合、ファイル名名指しでパブリッシュしようとした場合にも、
			// 　実在する親ディレクトリに遡ってスキャンしてしまうため、無駄に処理に時間がかかってしまっていた。
			// while( !$this->px->fs()->is_dir('./'.$path) ){
			// 	$path = $this->px->fs()->normalize_path(dirname($path).'/');
			// }
			array_push($rtn, $path);
		}
		return $rtn;
	}


	/**
	 * パブリッシュ先ディレクトリを取得
	 */
	private function get_path_publish_dir(){
		if( !strlen( $this->px->conf()->path_publish_dir ?? "" ) ){
			return false;
		}
		$tmp_path = $this->px->fs()->get_realpath( $this->px->conf()->path_publish_dir.'/' );
		if( !$this->px->fs()->is_dir( $tmp_path ) ){
			return false;
		}
		if( !$this->px->fs()->is_writable( $tmp_path ) ){
			return false;
		}
		return $tmp_path;
	}

	/**
	 * パブリッシュをロックする。
	 *
	 * @return bool ロック成功時に `true`、失敗時に `false` を返します。
	 */
	private function lock(){
		$lockfilepath = $this->path_lockfile;
		$timeout_limit = 5;

		if( !$this->px->fs()->is_dir( dirname( $lockfilepath ) ) ){
			$this->px->fs()->mkdir_r( dirname( $lockfilepath ) );
		}

		// PHPのFileStatusCacheをクリア
		clearstatcache();

		$i = 0;
		while( $this->is_locked() ){
			$i ++;
			if( $i >= $timeout_limit ){
				return false;
				break;
			}
			sleep(1);

			// PHPのFileStatusCacheをクリア
			clearstatcache();
		}
		$src = '';
		$src .= 'ProcessID='.getmypid()."\r\n";
		$src .= @date( 'c', time() )."\r\n";
		$RTN = $this->px->fs()->save_file( $lockfilepath , $src );

		// 割り込みを検証
		clearstatcache();
		sleep(1);
		clearstatcache();
		if($src !== file_get_contents( $lockfilepath )){
			return false;
		}

		return	$RTN;
	}

	/**
	 * パブリッシュがロックされているか確認する。
	 *
	 * @return bool ロック中の場合に `true`、それ以外の場合に `false` を返します。
	 */
	private function is_locked(){
		$lockfilepath = $this->path_lockfile;
		$lockfile_expire = 60*30;//有効期限は30分

		// PHPのFileStatusCacheをクリア
		clearstatcache();

		if( $this->px->fs()->is_file($lockfilepath) ){
			if( ( time() - filemtime($lockfilepath) ) > $lockfile_expire ){
				// 有効期限を過ぎていたら、ロックは成立する。
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * パブリッシュロックを解除する。
	 *
	 * @return bool ロック解除成功時に `true`、失敗時に `false` を返します。
	 */
	private function unlock(){
		$lockfilepath = $this->path_lockfile;

		clearstatcache();
		if( !$this->px->fs()->is_file( $lockfilepath ) ){
			return true;
		}

		return unlink( $lockfilepath );
	}

	/**
	 * パブリッシュロックファイルの更新日を更新する。
	 *
	 * @return bool 成功時に `true`、失敗時に `false` を返します。
	 */
	private function touch_lockfile(){
		$lockfilepath = $this->path_lockfile;

		clearstatcache();
		if( !is_file( $lockfilepath ) ){
			return false;
		}

		return touch( $lockfilepath );
	}

	/**
	 * パス文字列に新しいパラメータをマージする
	 * @param string $path マージ元のパス
	 * @param array $params マージするパラメータ
	 * @return string マージ後のパス
	 */
	private function merge_params( $path, $params ){

		$query_string = null;
		if( isset($params) && (is_array($params) || is_object($params)) ){
			$query_string = http_build_query( $params );
		}
		if( !strlen(''.$query_string) ){
			return $path;
		}

		$parsed_url_fin = parse_url($path);
		$path = $this->px->fs()->normalize_path( $parsed_url_fin['path'] );

		// パラメータをパスに付加
		if( array_key_exists('query', $parsed_url_fin) && strlen(''.$parsed_url_fin['query']) ){
			$query_string = $parsed_url_fin['query'].'&'.$query_string;
		}
		if( strlen(''.$query_string) ){
			$path .= '?'.$query_string;
		}

		// ハッシュが付いていた場合は復元する
		if( array_key_exists('fragment', $parsed_url_fin) && strlen(''.$parsed_url_fin['fragment']) ){
			$path .= '#'.$parsed_url_fin['fragment'];
		}

		return $path;
	}


	/**
	 * コンテンツJSONを保存する
	 */
	private function save_content_json($json){
		static $realpath_plugin_files;
		if( is_null($realpath_plugin_files) ){
			$realpath_plugin_files = $this->px->realpath_plugin_private_cache();
		}
		$this->px->fs()->mkdir_r($realpath_plugin_files.'contents/');

		$json->h2 = '';
		$json->h3 = '';
		$json->h4 = '';

		// HTMLをパース
		$html = $this->parse_html( $json->content );
		if($html === false){
			$json->content = strip_tags($json->content);
			$json->content = preg_replace('/[ \t\r\n]+/', " ", $json->content);
			$json->content = trim($json->content);
		}else{

			// h1を抽出
			if( !strlen($json->title ?? '') ){
				$headding_array = array();
				$ret = $html->find('h1');
				foreach( $ret as $retRow ){
					array_push($headding_array, trim(strip_tags($retRow->innertext)));
				}
				if( !count($headding_array) ){
					$ret = $html->find('title');
					foreach( $ret as $retRow ){
						array_push($headding_array, trim(strip_tags($retRow->innertext)));
					}
				}
				$json->title = implode(' ', $headding_array);
			}

			// コンテンツを抽出
			$contents_array = array();
			$ret = $html->find($this->main->plugin_conf()->contents_area_selector);
			foreach( $ret as $retRow ){
				array_push($contents_array, $retRow->outertext);
			}

			// 抽出されたHTMLを再パース
			$html = $this->parse_html( '<div>'.implode("\n", $contents_array).'</div>' );

			// 除外コンテンツ
			if( is_array($this->main->plugin_conf()->ignored_contents_selector) && count($this->main->plugin_conf()->ignored_contents_selector) ){
				foreach($this->main->plugin_conf()->ignored_contents_selector as $ignored_contents_selector ){
					$ret = $html->find($ignored_contents_selector);
					foreach( $ret as $retRow ){
						$retRow->outertext = '';
					}
				}
			}

			// style要素を削除
			$ret = $html->find('style');
			foreach( $ret as $retRow ){
				$retRow->outertext = '';
			}
			// link要素を削除
			$ret = $html->find('link');
			foreach( $ret as $retRow ){
				$retRow->outertext = '';
			}
			// script要素を削除
			$ret = $html->find('script');
			foreach( $ret as $retRow ){
				$retRow->outertext = '';
			}

			// 削除後のHTMLを再パース
			$html = $this->parse_html( $html->outertext );

			// 見出しを抽出
			$ret = $html->find('h2');
			$headding_array = array();
			foreach( $ret as $retRow ){
				array_push($headding_array, trim(strip_tags($retRow->innertext)));
			}
			$json->h2 = implode(' ', $headding_array);

			$ret = $html->find('h3');
			$headding_array = array();
			foreach( $ret as $retRow ){
				array_push($headding_array, trim(strip_tags($retRow->innertext)));
			}
			$json->h3 = implode(' ', $headding_array);

			$ret = $html->find('h4');
			$headding_array = array();
			foreach( $ret as $retRow ){
				array_push($headding_array, trim(strip_tags($retRow->innertext)));
			}
			$json->h4 = implode(' ', $headding_array);

			// 検索用にコンテンツを整形
			$json->content = $html->outertext;
			$json->content = strip_tags($json->content);
			$json->content = preg_replace('/[ \t\r\n]+/', " ", $json->content);
			$json->content = trim($json->content);
		}

		if(!strlen($json->content ?? '')){
			return;
		}

		$this->px->fs()->save_file($realpath_plugin_files.'contents/'.urlencode($json->href).'.json', json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
	}

	/**
	 * 除外されたパスか調べる
	 * @param string $path 検査対象のパス
	 * @return boolean 除外されていたら true, 除外されていない場合は false
	 */
	private function is_ignored_path( $path ){
		$ignored_path = $this->main->plugin_conf()->ignored_path;
		if( !is_array($ignored_path) ){
			return false;
		}
		foreach($ignored_path as $pattern){
			// 完全一致による設定を評価
			if( $pattern == $path ){
				return true;
			}

			// 正規表現による設定を評価
			$is_pattern_regexp = false;
			if( preg_match('/^(.).*\1[imsxADSUXJun]*$/', $pattern) ){
				// 正規表現パターンとして妥当かどうかを判定する
				$is_pattern_regexp = true;
			}
			if( $is_pattern_regexp && preg_match($pattern, $path) ){
				return true;
			}
		}
		return false;
	}

	/**
	 * HTMLをパースする
	 *
	 * @param string $src HTMLコード
	 * @return object $html Simple HTML DOM オブジェクト
	 */
	private function parse_html( $src ){
		$html = \picklesFramework2\px2SiteSearch\str_get_html(
			$src,
			false, // $lowercase
			false, // $forceTagsClosed
			DEFAULT_TARGET_CHARSET, // $target_charset
			false, // $stripRN
			DEFAULT_BR_TEXT, // $defaultBRText
			DEFAULT_SPAN_TEXT // $defaultSpanText
		);
		return $html;
	}

}
