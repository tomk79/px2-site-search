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

		$this->plugin_conf = (object) $this->plugin_conf;
		$this->plugin_conf->path_client_assets_dir = $this->plugin_conf->path_client_assets_dir ?? '/common/site_search_index/';
	}

	public function px(){
		return $this->px;
	}

	public function plugin_conf(){
		return $this->plugin_conf;
	}

	/**
	 * インデックスファイルを生成する
	 */
	public function createIndex(){
		$createIndex = new createIndex\createIndex($this);
		return $createIndex->execute();
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
                "href" => $json->href ?? null,
                "title" => $json->page_info->title ?? $json->title ?? '',
                "h2" => $json->h2 ?? '',
                "h3" => $json->h3 ?? '',
                "h4" => $json->h4 ?? '',
                "content" => $json->content ?? '',
            ));
        }

		$realpath_controot = $this->px->fs()->normalize_path( $this->px->fs()->get_realpath( $this->px->get_realpath_docroot().$this->px->get_path_controot() ) );
		$realpath_public_base = $realpath_controot.$this->plugin_conf()->path_client_assets_dir.'/';

		$this->px->fs()->mkdir_r($realpath_public_base);
		$this->px->fs()->save_file($realpath_public_base.'index.json', json_encode($integrated, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
		$this->px->fs()->copy_r(__DIR__.'/../public/', $realpath_public_base.'assets/');
    }

}
