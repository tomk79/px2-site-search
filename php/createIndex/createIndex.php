<?php
/**
 * px2-site-search
 */
namespace picklesFramework2\px2SiteSearch\createIndex;

/**
 * PX Commands "site_search.create_index"
 */
class createIndex {

	/** Picklesオブジェクト */
	private $px;

	/** プラグイン設定 */
	private $plugin_conf;

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

	/** 処理済みのパス一覧 */
	private $paths_done = array();

	/** Extension をマッチさせる正規表現 */
	private $preg_exts;

	/**
	 * constructor
	 * @param object $px Picklesオブジェクト
	 * @param object $options プラグイン設定
	 */
	public function __construct( $px, $options ){
		// プラグイン設定の初期化
		if( !is_object($options) ){
			$options = json_decode('{}');
		}
		if( !isset($options->paths_ignore) || !is_array($options->paths_ignore) ){
			$options->paths_ignore = array();
		}
		if( !isset($options->devices) || !is_array($options->devices) ){
			$options->devices = array();
		}
		foreach( $options->devices as $device ){
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
		if( !property_exists($options, 'skip_default_device') ){
			$options->skip_default_device = false;
		}
		if( !property_exists($options, 'publish_vendor_dir') ){
			$options->publish_vendor_dir = false;
		}

		$this->px = $px;
		$this->plugin_conf = $options;
		$this->path_rewriter = new path_rewriter( $px, $this->plugin_conf );
		$this->tmp_publish_dir = new tmp_publish_dir( $px, $this->plugin_conf );
		$this->device_target_path = new device_target_path( $px, $this->plugin_conf );

		$this->path_tmp_publish = $px->fs()->get_realpath( $px->get_realpath_homedir().'_sys/ram/publish/' );
		$this->path_lockfile = $this->path_tmp_publish.'applock.txt';
		if( $this->get_path_publish_dir() !== false ){
			$this->path_publish_dir = $this->get_path_publish_dir();
		}
		$this->domain = $px->conf()->domain;
		$this->path_controot = $px->conf()->path_controot;

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
		print 'publish directory: '.$this->path_publish_dir."\n";
		print 'domain: '.$this->domain."\n";
		print 'docroot directory: '.$this->path_controot."\n";
		print 'ignore: '.join(', ', $this->plugin_conf->paths_ignore)."\n";
		print 'region: '.join(', ', $this->paths_region)."\n";
		print 'ignore (tmp): '.join(', ', $this->paths_ignore)."\n";
		print 'keep cache: '.($this->flg_keep_cache ? 'true' : 'false')."\n";
		print 'devices:'."\n";
		foreach($this->plugin_conf->devices as $key=>$device){
			print '  - device['.$key.']:'."\n";
			print '    - user_agent: '.$device->user_agent."\n";
			print '    - path_publish_dir: '.$device->path_publish_dir."\n";
			print '    - path_rewrite_rule: '.$device->path_rewrite_rule."\n";
			print '    - paths_target: '.(is_array($device->paths_target) ? join(', ', $device->paths_target) : '')."\n";
			print '    - paths_ignore: '.(is_array($device->paths_ignore) ? join(', ', $device->paths_ignore) : '')."\n";
			print '    - rewrite_direction: '.$device->rewrite_direction."\n";
		}
		print 'skip default device: '.($this->plugin_conf->skip_default_device ? 'true' : 'false')."\n";
		print 'publish vendor directory: '.($this->plugin_conf->publish_vendor_dir ? 'true' : 'false')."\n";
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
		$cnt_done = count( $this->paths_done );
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
		print "\n";
		print '============'."\n";
		print '## Start publishing'."\n";
		print "\n";
		print $this->cli_report();
		print "\n";

		file_put_contents($this->path_tmp_publish.'timelog.txt', 'Started at: '.date('c', $total_time)."\n", FILE_APPEND); // 2020-04-01 @tomk79 記録するようにした。

		$device_list = $this->plugin_conf->devices;
		foreach($device_list as $device_num => $device_info){
			$device_list[$device_num]->user_agent = trim($device_info->user_agent).'/PicklesCrawler';
			if($this->px->fs()->is_dir($device_info->path_publish_dir)){
				$device_list[$device_num]->path_publish_dir = $this->px->fs()->get_realpath( $device_info->path_publish_dir );
			}else{
				$device_list[$device_num]->path_publish_dir = false;
			}
			$device_list[$device_num]->path_rewrite_rule = $this->path_rewriter->normalize_callback( $device_list[$device_num]->path_rewrite_rule ?? null );
		}
		if( !$this->plugin_conf->skip_default_device ){
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
		// var_dump($device_list);

		if( $this->plugin_conf->publish_vendor_dir ){
			// --------------------------------------
			// vendorディレクトリのコピーを作成する
			if( !$this->is_region_path( '/vendor/' ) ){
				// vendor が範囲外の場合には、実行しない。
			}else{
				print ' Copying vendor directory...'."\n";
				$vendorDir = new vendor_dir( $this->px, $this->plugin_conf );
				$vendorDir->copy_vendor_to_publish_dirs( $device_list );
				print ' Done!'."\n";
				print "\n";
			}
		}

		while(1){
			set_time_limit(5*60);
			flush();
			if( !count( $this->paths_queue ) ){
				break;
			}
			foreach( $this->paths_queue as $path=>$val ){break;}
			print '------------'."\n";
			print $path."\n";

			foreach($device_list as $device_num => $device_info){
				// var_dump($device_info);
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
				if( $path_type != 'normal' && $path_type !== false ){
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
							if( !$this->px->fs()->mkdir_r( dirname( $this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot.$path_rewrited ) ) ){
								$status_code = 500;
								$this->alert_log(array( @date('c'), $path_rewrited, 'FAILED to making parent directory.' ));
								break;
							}
							if( !$this->px->fs()->copy( dirname($_SERVER['SCRIPT_FILENAME']).$path , $this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot.$path_rewrited ) ){
								$status_code = 500;
								$this->alert_log(array( @date('c'), $path_rewrited, 'FAILED to copying file.' ));
								break;
							}
							$status_code = 200;
							break;

						case 'direct':
						default:
							// pickles execute
							print $ext.' -> '.$proc_type."\n";

							if( !isset( $device_info->params ) ){
								$device_info->params = null;
							}

							$bin = $this->px->internal_sub_request( $this->merge_params($path, $device_info->params), array('output'=>'json', 'user_agent'=>$device_info->user_agent), $return_var );
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
							$this->px->fs()->mkdir_r( dirname( $this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot.$path_rewrited ) );
							$this->px->fs()->save_file( $this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot.$path_rewrited, base64_decode( $bin->body_base64 ?? null ) );
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

					// パスの書き換え
					if( is_file($this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot.$path_rewrited) ){
						$src = $this->px->fs()->read_file( $this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot.$path_rewrited );
						$path_resolver = new path_resolver( $this->px, $this->plugin_conf, $this->path_rewriter, $device_info, $path, $path_rewrited );
						$src = $path_resolver->resolve($src);
						$this->px->fs()->save_file( $this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot.$path_rewrited, $src );
					}
					// / パスの書き換え

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

				if( !empty( $device_info->path_publish_dir ) ){
					// パブリッシュ先ディレクトリに都度コピー
					if( $this->px->fs()->is_file( $this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot.$path_rewrited ) ){
						$this->px->fs()->mkdir_r( dirname( $device_info->path_publish_dir.$this->path_controot.$path_rewrited ) );
						$this->px->fs()->copy(
							$this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot.$path_rewrited ,
							$device_info->path_publish_dir.$this->path_controot.$path_rewrited
						);
						print ' -> copied to publish dir'."\n";
					}
				}

			} // multi device

			unset($this->paths_queue[$path]);
			$this->paths_done[$path] = true;
			print $this->cli_report();

			$this->touch_lockfile();

			if( !count( $this->paths_queue ) ){
				break;
			}
		}

		print "\n";

		if( !empty( $this->path_publish_dir ) ){
			// パブリッシュ先ディレクトリを同期
			print '============'."\n";
			print '## Sync to publish directory.'."\n";
			print "\n";
			$publish_dir_list = $this->tmp_publish_dir->get_publish_dir_list();
			foreach($publish_dir_list as $path_publish_dir => $publish_dir_idx){
				$htdocs_sufix = $publish_dir_idx;
				if(!$htdocs_sufix){ $htdocs_sufix = '';}
				set_time_limit(30*60);
				foreach( $this->paths_region as $path_region ){
					$this->sync_dir(
						$this->path_tmp_publish.'/htdocs'.$htdocs_sufix.$this->path_controot ,
						$path_publish_dir.$this->path_controot ,
						$path_region
					);
				}
			}
		}
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
				// var_dump($row);
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

		$this->unlock();//ロック解除

		print $this->cli_footer();
		exit;
	}

	/**
	 * ディレクトリを同期する。
	 *
	 * @param string $path_sync_from 同期元のルートディレクトリ
	 * @param string $path_sync_to 同期先のルートディレクトリ
	 * @param string $path_region ルート以下のパス
	 * @return bool 常に `true` を返します。
	 */
	private function sync_dir( $path_sync_from , $path_sync_to, $path_region ){
		print 'Copying files and directories...';
		$this->sync_dir_copy_r( $path_sync_from , $path_sync_to, $path_region );
		print "\n";
		print 'Deleting removed files and directories...';
		$this->sync_dir_compare_and_cleanup( $path_sync_to , $path_sync_from, $path_region );
		print "\n";
		return true;
	}

	/**
	 * ディレクトリを複製する(下層ディレクトリも全てコピー)
	 *
	 * ただし、ignore指定されているパスに対しては操作を行わない。
	 *
	 * @param string $from コピー元ファイルのパス
	 * @param string $to コピー先のパス
	 * @param string $path_region ルート以下のパス
	 * @param int $perm 保存するファイルに与えるパーミッション
	 * @return bool 成功時に `true`、失敗時に `false` を返します。
	 */
	private function sync_dir_copy_r( $from, $to, $path_region, $perm = null ){
		static $count = 0;
		if( $count%100 == 0 ){print '.';}
		$count ++;

		$from = $this->px->fs()->localize_path($from);
		$to   = $this->px->fs()->localize_path($to  );
		$path_region = $this->px->fs()->localize_path($path_region);

		$result = true;

		if( $this->px->fs()->is_file( $from.$path_region ) ){
			if( $this->px->fs()->mkdir_r( dirname( $to.$path_region ) ) ){
				if( $this->px->is_ignore_path( $path_region ) ){
					// ignore指定されているパスには、操作しない。
				}elseif( !$this->is_region_path( $path_region ) ){
					// 範囲外のパスには、操作しない。
				}else{
					if( !$this->px->fs()->copy( $from.$path_region , $to.$path_region , $perm ) ){
						$result = false;
					}
				}
			}else{
				$result = false;
			}
		}elseif( $this->px->fs()->is_dir( $from.$path_region ) ){
			if( !$this->px->fs()->is_dir( $to.$path_region ) ){
				if( $this->px->is_ignore_path( $path_region ) ){
					// ignore指定されているパスには、操作しない。
				}elseif( !$this->is_region_path( $path_region ) ){
					// 範囲外のパスには、操作しない。
				}else{
					if( !$this->px->fs()->mkdir_r( $to.$path_region ) ){
						$result = false;
					}
				}
			}
			$itemlist = $this->px->fs()->ls( $from.$path_region );
			foreach( $itemlist as $Line ){
				if( $Line == '.' || $Line == '..' ){ continue; }
				if( $this->px->fs()->is_dir( $from.$path_region.DIRECTORY_SEPARATOR.$Line ) ){
					if( $this->px->fs()->is_file( $to.$path_region.DIRECTORY_SEPARATOR.$Line ) ){
						continue;
					}elseif( !$this->px->fs()->is_dir( $to.$path_region.DIRECTORY_SEPARATOR.$Line ) ){
						if( $this->px->is_ignore_path( $path_region.DIRECTORY_SEPARATOR.$Line ) ){
							// ignore指定されているパスには、操作しない。
						}elseif( !$this->is_region_path( $path_region.DIRECTORY_SEPARATOR.$Line ) ){
							// 範囲外のパスには、操作しない。
						}else{
							if( !$this->px->fs()->mkdir_r( $to.$path_region.DIRECTORY_SEPARATOR.$Line ) ){
								$result = false;
							}
						}
					}
					if( !$this->sync_dir_copy_r( $from , $to, $path_region.DIRECTORY_SEPARATOR.$Line , $perm ) ){
						$result = false;
					}
					continue;
				}elseif( $this->px->fs()->is_file( $from.$path_region.DIRECTORY_SEPARATOR.$Line ) ){
					if( !$this->sync_dir_copy_r( $from, $to, $path_region.DIRECTORY_SEPARATOR.$Line , $perm ) ){
						$result = false;
					}
					continue;
				}
			}
		}

		return $result;
	}

	/**
	 * ディレクトリの内部を比較し、$comparisonに含まれない要素を$targetから削除する。
	 *
	 * ただし、ignore指定されているパスに対しては操作を行わない。
	 *
	 * @param string $target クリーニング対象のディレクトリパス
	 * @param string $comparison 比較するディレクトリのパス
	 * @param string $path_region ルート以下のパス
	 * @return bool 成功時 `true`、失敗時 `false` を返します。
	 */
	private function sync_dir_compare_and_cleanup( $target , $comparison, $path_region ){
		static $count = 0;
		if( $count%100 == 0 ){print '.';}
		$count ++;

		if( is_null( $comparison ) || is_null( $target ) ){
			return false;
		}

		$target = $this->px->fs()->localize_path($target);
		$comparison = $this->px->fs()->localize_path($comparison);
		$path_region = $this->px->fs()->localize_path($path_region);
		$flist = array();

		// 先に、ディレクトリ内をスキャンする
		if( $this->px->fs()->is_dir( $target.$path_region ) ){
			$flist = $this->px->fs()->ls( $target.$path_region );
			foreach ( $flist as $Line ){
				if( $Line == '.' || $Line == '..' ){ continue; }
				$this->sync_dir_compare_and_cleanup( $target , $comparison, $path_region.DIRECTORY_SEPARATOR.$Line );
			}
			$flist = $this->px->fs()->ls( $target.$path_region );
		}


		if( !file_exists( $comparison.$path_region ) && file_exists( $target.$path_region ) ){
			if( $this->px->is_ignore_path( $path_region ) ){
				// ignore指定されているパスには、操作しない。
				return true;
			}elseif( !$this->is_region_path( $path_region ) ){
				// 範囲外のパスには、操作しない。
				return true;
			}
			if( $this->px->fs()->is_dir( $target.$path_region ) ){
				if( !count($flist) ){
					// ディレクトリの場合は、内容が空でなければ削除しない。
					$this->px->fs()->rm( $target.$path_region );
				}
			}else{
				$this->px->fs()->rm( $target.$path_region );
			}

			return true;
		}

		return true;
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
		}else{
			// 一時パブリッシュディレクトリをクリーニング
			echo '-- cleaning "publish"'."\n";
			$this->cleanup_tmp_publish_dir( $this->path_tmp_publish );
		}

		return true;
	}

	/**
	 * 一時パブリッシュディレクトリをクリーニング
	 * @param string $path クリーニング対象のパス
	 * @param string $localpath $pathの仮想のパス (再帰処理のために使用)
	 */
	private function cleanup_tmp_publish_dir( $path, $localpath = null ){
		$count = 0;
		$ls = $this->px->fs()->ls($path.$localpath);
		foreach( $ls as $basename ){
			if( $localpath.$basename == '.gitkeep' ){
				continue;
			}
			if( $this->px->fs()->is_dir($path.$localpath.$basename) ){
				$count += $this->cleanup_tmp_publish_dir( $path, $localpath.$basename.DIRECTORY_SEPARATOR );

				$i = 0;
				print 'rmdir '.$this->px->fs()->get_realpath( $path.$localpath.$basename );
				while(1){
					$i ++;
					if( $this->px->fs()->rmdir($path.$localpath.$basename) ){
						break;
					}
					if($i > 5){
						print ' [FAILED]';
						break;
					}
					sleep(1);
				}
				print "\n";
				$count ++;

			}else{
				clearstatcache();
				if( $this->px->fs()->get_realpath($path.$localpath.$basename) == $this->path_lockfile ){
					// パブリッシュロックファイルは消さない
				}else{
					$i = 0;
					print 'rm '.$this->px->fs()->get_realpath( $path.$localpath.$basename );
					while(1){
						$i ++;
						if( $this->px->fs()->rm($path.$localpath.$basename) ){
							break;
						}
						if($i > 5){
							print ' [FAILED]';
							break;
						}
						sleep(1);
					}
					print "\n";
					$count ++;
				}
			}
		}

		if( is_null($localpath) ){
			$this->px->fs()->save_file( $path.$localpath.'.gitkeep', '' );
		}
		return $count;
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
			// var_dump($row);
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
			// var_dump($preg_pattern);
			if( preg_match( '/^'.$preg_pattern.'$/s' , $path_child ) ){
				// var_dump($path_child);
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
		if( array_key_exists($path, $this->paths_queue) ){
			// 登録済み
			return false;
		}
		if( array_key_exists($path, $this->paths_done) ){
			// 処理済み
			return false;
		}
		$this->paths_queue[$path] = true;
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

		foreach( $this->plugin_conf->paths_ignore as $row ){
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
			// var_dump($path);
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

}
