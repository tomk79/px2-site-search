<?php
/**
 * px2-site-search
 */
namespace picklesFramework2\px2SiteSearch\cce;

/**
 * main.php
 */
class main{

    /** $px */
    private $px;

    /** $options */
    private $options;

    /** $cceAgent */
    private $cceAgent;

    /**
     * コンストラクタ
     * @param object $px Pickles 2 オブジェクト
     * @param object $options 設定オプション
     * @param object $cceAgent 管理画面拡張エージェントオブジェクト
     */
    public function __construct($px, $options, $cceAgent){
        $this->px = $px;
        $this->options = $options;
        $this->cceAgent = $cceAgent;
    }

    /**
     * 管理機能名を取得する
     */
    public function get_label(){
        return 'サイト内検索';
    }

    /**
     * フロントエンド資材の格納ディレクトリを取得する
     */
    public function get_client_resource_base_dir(){
        return __DIR__.'/../front/';
    }

    /**
     * 管理画面にロードするフロント資材のファイル名を取得する
     */
    public function get_client_resource_list(){
        $rtn = array();
        $rtn['css'] = array();
        array_push($rtn['css'], 'siteSearchCceFront.css');
        $rtn['js'] = array();
        array_push($rtn['js'], 'siteSearchCceFront.js');
        return $rtn;
    }

    /**
     * 管理画面を初期化するためのJavaScript関数名を取得する
     */
    public function get_client_initialize_function(){
        return 'window.siteSearchCceFront';
    }

    /**
     * General Purpose Interface (汎用API)
     */
    public function gpi($request){
        switch($request->command){
            case 'test-gpi-call':
                $this->cceAgent->async(array(
                    'type'=>'gpi',
                    'request' => array(
                        'command' => 'test-async',
                    ),
                ));
                return 'Test GPI Call Successful.';

            case 'test-async':
                $this->cceAgent->broadcast(array(
                    'message'=>'Hello Broadcast Message !',
                ));
                return 'Test GPI Call Successful.';
        }
        return false;
    }
}