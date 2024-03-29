<?php
/**
 * px2-site-search
 */
namespace picklesFramework2\px2SiteSearch;
use TeamTNT\TNTSearch\TNTSearch;

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
		$this->plugin_conf->engine_type = $this->plugin_conf->engine_type ?? 'client';
		$this->plugin_conf->path_client_assets_dir = $this->plugin_conf->path_client_assets_dir ?? '/common/site_search_index/';
		$this->plugin_conf->path_private_data_dir = $this->plugin_conf->path_private_data_dir ?? '/_sys/site_search_index/';
		$this->plugin_conf->contents_area_selector = $this->plugin_conf->contents_area_selector ?? '.contents';
		$this->plugin_conf->ignored_contents_selector = $this->plugin_conf->ignored_contents_selector ?? array();
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
		$realpath_controot = $this->px->fs()->normalize_path( $this->px->fs()->get_realpath( $this->px->get_realpath_docroot().$this->px->get_path_controot() ) );
		$realpath_public_base = $realpath_controot.$this->plugin_conf()->path_client_assets_dir.'/';
		$realpath_homedir = $this->px->fs()->normalize_path( $this->px->fs()->get_realpath( $this->px->get_realpath_homedir() ) );
		$realpath_private_data_base = $realpath_homedir.$this->plugin_conf()->path_private_data_dir.'/';

		$this->px->fs()->copy_r(__DIR__.'/../public/assets/', $realpath_public_base.'assets/');

		// --------------------------------------
		// client side script
		$px2SiteSearchJs = $this->px->fs()->read_file(__DIR__.'/../public/assets/px2-site-search.js');
		$px2SiteSearchJs = preg_replace('/\$____engine_type____/s', $this->plugin_conf()->engine_type, $px2SiteSearchJs);
		$px2SiteSearchJs = preg_replace('/\$____data-path-controot____/s', $this->px->conf()->path_controot, $px2SiteSearchJs);
		$px2SiteSearchJs = preg_replace('/\$____lang____/s', $this->px->lang(), $px2SiteSearchJs);
		$this->px->fs()->save_file($realpath_public_base.'assets/px2-site-search.js', $px2SiteSearchJs);

		// --------------------------------------
		// initialize FlexSearch
        $integrated = (object) array(
            "contents" => array(),
        );

		// --------------------------------------
		// initialize TNTSearch
		if( $this->plugin_conf->engine_type == 'paprika' ){
			$searchPhp = $this->px->fs()->read_file(__DIR__.'/../public/search.php');
			$searchPhp = preg_replace('/\$____path_client_assets_dir____/s', $this->plugin_conf()->path_client_assets_dir, $searchPhp);
			$searchPhp = preg_replace('/\$____path_private_data_dir____/s', $this->plugin_conf()->path_private_data_dir, $searchPhp);
			$this->px->fs()->save_file($realpath_public_base.'search.php', $searchPhp);
		}

		$this->px->fs()->rm($realpath_private_data_base.'tntsearch/');
		if( $this->plugin_conf->engine_type == 'paprika' ){
			if( $this->px->fs()->mkdir_r($realpath_private_data_base.'tntsearch/') ){
				// TNT Search データディレクトリを初期化する
				touch($realpath_private_data_base.'tntsearch/articles.sqlite');

				// NOTE: TNT Search はデータベースからコンテンツを取得してインデックスを作成する。
				// 本作ではデータベースにコンテンツは格納しないので本来必要ないが、
				// データベースがないとエラーが起きるので、ダミーだが作成している。
				$pdo = new \PDO(
					'sqlite'.':'.$realpath_private_data_base.'tntsearch/articles.sqlite',
				);
				$sql = 'CREATE TABLE articles (
					id VARCHAR(255) NOT NULL,
					title TEXT NOT NULL,
					article TEXT NOT NULL,
					PRIMARY KEY (id));';
				$stmt = $pdo->prepare($sql);
				$stmt->execute();
			}

			$tnt = new TNTSearch;
			$tnt->loadConfig([
				'driver'    => 'sqlite',
				'database'  => $realpath_private_data_base.'tntsearch/articles.sqlite',
				'storage'   => $realpath_private_data_base.'tntsearch/',
				'stemmer'   => \TeamTNT\TNTSearch\Stemmer\PorterStemmer::class
			]);
			$indexer = $tnt->createIndex('index.sqlite');
			$tnt->selectIndex("index.sqlite");
			$index = $tnt->getIndex();
		}

		// --------------------------------------
		// making index
        foreach($json_file_list as $idx => $json_file){
            $json = json_decode( $this->px->fs()->read_file($realpath_plugin_private_cache.'contents/'.$json_file) );

			// FlexSearch
            array_push($integrated->contents, (object) array(
                "h" => $json->href ?? null, // href
                "t" => $json->page_info->title ?? $json->title ?? '', // title
                "h2" => $json->h2 ?? '',
                "h3" => $json->h3 ?? '',
                "h4" => $json->h4 ?? '',
                "c" => $json->content ?? '', // content
            ));

			// TNTSearch
			if( $this->plugin_conf->engine_type == 'paprika' ){
				$index->insert(array(
					'id' => $idx,
					"href" => $json->href ?? null,
					"title" => $json->page_info->title ?? $json->title ?? '',
					"h2" => $json->h2 ?? '',
					"h3" => $json->h3 ?? '',
					"h4" => $json->h4 ?? '',
					"article" => $json->content ?? '', // content
				));
			}
        }

		$this->px->fs()->mkdir_r($realpath_public_base);
		$this->px->fs()->save_file($realpath_public_base.'index.json', json_encode($integrated, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
	}
}
