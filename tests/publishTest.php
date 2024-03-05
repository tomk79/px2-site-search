<?php
/**
 * Test for pickles2/px2-paprika
 */

class publishTest extends PHPUnit\Framework\TestCase {
	private $fs;

	/**
	 * setup
	 */
	public function setUp() : void{
		$this->fs = new \tomk79\filesystem();
	}

	/**
	 * パブリッシュ後に出力されたdistコードのテスト
	 */
	public function testPublish(){

		// Pickles 2 実行
		$output = $this->passthru( [
			'php',
			__DIR__.'/testdata/standard/.px_execute.php' ,
			'/?PX=publish.run' ,
		] );

		// トップページのソースコードを検査
		$indexHtml = $this->fs->read_file( __DIR__.'/testdata/standard/px-files/dist/index.html' );

		// プレビュー環境の sample.php を実行
		$output = $this->passthru( [
			'php',
			__DIR__.'/testdata/standard/.px_execute.php',
			'-u', "Mozilla/5.0",
			'/basic/php_api-ajax_files/apis/sample.php'
		] );
		$json = json_decode($output);
		$this->assertTrue( is_null($json->paprikaConf->undefined) );
		$this->assertEquals( $json->paprikaConf->sample1, 'config_local.php' );
		$this->assertFalse( property_exists($json->paprikaConf->sample2, 'prop1') );
		$this->assertEquals( $json->paprikaConf->sample2->prop2, 'config_local.php' );
		$this->assertEquals( $json->paprikaConf->sample3, 'config.php' );
		$this->assertEquals( $json->paprikaConf->prepend1, 1 );
		$this->assertEquals( $json->paprikaConf->prepend2, 2 );
		$this->assertEquals( $json->paprikaConf->custom_func_a, 'called' );
		$this->assertEquals( $json->paprikaConf->dotEnvLoaded, 'DotEnvLoaded' );

		// 出力された sample.php を実行
		$output = $this->passthru( [
			'php',
			__DIR__.'/testdata/standard/px-files/dist/basic/php_api-ajax_files/apis/sample.php'
		] );
		$json = json_decode($output);
		$this->assertTrue( is_null($json->paprikaConf->undefined) );
		$this->assertEquals( $json->paprikaConf->sample1, 'config_local.php' );
		$this->assertFalse( property_exists($json->paprikaConf->sample2, 'prop1') );
		$this->assertEquals( $json->paprikaConf->sample2->prop2, 'config_local.php' );
		$this->assertEquals( $json->paprikaConf->sample3, 'config.php' );
		$this->assertEquals( $json->paprikaConf->prepend1, 1 );
		$this->assertEquals( $json->paprikaConf->prepend2, 2 );
		$this->assertEquals( $json->paprikaConf->custom_func_a, 'called' );
		$this->assertEquals( $json->paprikaConf->dotEnvLoaded, 'DotEnvLoaded' );

		// php_page.php のソースコードを検査
		$indexHtml = $this->fs->read_file( __DIR__.'/testdata/standard/px-files/dist/basic/php_page.php' );
		$this->assertFalse( !!preg_match('/\!doctype/si', $indexHtml) );

		// 出力された php_page.php を実行
		$output = $this->passthru( [
			'php',
			__DIR__.'/testdata/standard/px-files/dist/basic/php_page.php'
		] );
		$this->assertTrue( !!preg_match('/\!doctype/si', $output) );

		// 後始末
		$output = $this->passthru( [
			'php',
			__DIR__.'/testdata/standard/.px_execute.php' ,
			'/?PX=clearcache' ,
		] );
	}


	/**
	 * コマンドを実行し、標準出力値を返す
	 * @param array $ary_command コマンドのパラメータを要素として持つ配列
	 * @return string コマンドの標準出力値
	 */
	private function passthru( $ary_command ){
		$cmd = array();
		foreach( $ary_command as $row ){
			$param = '"'.addslashes($row).'"';
			array_push( $cmd, $param );
		}
		$cmd = implode( ' ', $cmd );
		ob_start();
		passthru( $cmd );
		$bin = ob_get_clean();
		return $bin;
	}

}
