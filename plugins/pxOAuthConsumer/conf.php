<?php

/**
 * PX Plugin "pxOAuthConsumer"
 */
class pxplugin_pxOAuthConsumer_conf{
	private $px;
	private $conf_data = array(
		//  内部で使用するセッションキー
		'session_key'=>'PLUGIN_PX_OAUTH_CONSUMER',

		//  コールバックURLに付加するパラメータのキー
		'callback_param_key'=>'OAUTH_CALLBACK',

		//  サービスプロバイダー別の設定を記述
		'providers'=>array(
			'twitter'=>array(
				'consumer_key'    => '(type your APPs information)' ,
				'consumer_secret' => '(type your APPs information)' ,
			) ,
		) ,
	);

	/**
	 * コンストラクタ
	 * @param $px = PxFWコアオブジェクト
	 */
	public function __construct($px){
		$this->px = $px;
	}

	/**
	 * 設定内容をすべて取得する
	 */
	public function get_conf(){
		return $this->conf_data;
	}

	/**
	 * セッションキー設定を取得する
	 */
	public function get_session_key(){
		return $this->conf_data['session_key'];
	}

	/**
	 * コールバックパラメータキー設定を取得する
	 */
	public function get_callback_param_key(){
		return $this->conf_data['callback_param_key'];
	}

	/**
	 * プロバイダーの一覧を取得する
	 */
	public function get_provider_list(){
		return array_keys( $this->conf_data['providers'] );
	}

	/**
	 * 指定プロバイダーの設定情報のみを取得する
	 */
	public function get_provider_info($provider_name){
		return $this->conf_data['providers'][$provider_name];
	}

}

?>