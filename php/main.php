<?php
/**
 * px2-site-search
 */
namespace picklesFramework2\px2SiteSearch;

/**
 * main.php
 */
class main {

	/**
	 * Picklesオブジェクト
	 */
	private $px;

	/**
	 * プラグイン設定オブジェクト
	 */
	private $plugin_conf;

	/**
	 * constructor
	 * @param object $px Picklesオブジェクト
	 * @param object $plugin_conf プラグイン設定
	 */
	public function __construct( $px, $plugin_conf ){
		$this->px = $px;
		$this->plugin_conf = $plugin_conf;
	}

    /**
     * インデックスを統合する
     */
    public function integrate_index(){
		$realpath_plugin_private_cache = $this->px->realpath_plugin_private_cache();
        $json_file_list = $this->px->fs()->ls($realpath_plugin_private_cache.'contents/');
        $integrated = (object) array(
            "contents" => array(),
        );
        foreach($json_file_list as $json_file){
            $json = json_decode( $this->px->fs()->read_file($realpath_plugin_private_cache.'contents/'.$json_file) );
            array_push($integrated->contents, (object) array(
                "href" => $json->href,
                "title" => $json->page_info->title,
                "content" => $json->content,
            ));
        }

		$realpath_plugin_files = $this->px->realpath_plugin_files();
		$href_plugin_files = $this->px->path_plugin_files();
		$path_plugin_files = preg_replace('/^'.preg_quote($this->px->get_path_controot(), '/').'/', '/', $href_plugin_files);

		$this->px->fs()->save_file($realpath_plugin_files.'index.json', json_encode($integrated, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
		$this->px->fs()->copy_r(__DIR__.'/../public/', $realpath_plugin_files.'assets/');

        echo $this->px->internal_sub_request($path_plugin_files.'?PX=publish.run&keep_cache=1');
    }
}
