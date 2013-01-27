<?php

/**
 * PX Plugin "pxOAuthConsumer"
 */
class pxplugin_pxOAuthConsumer_register_object{
	private $px;
	private $conf;
	private $session;

	/**
	 * コンストラクタ
	 * @param $px = PxFWコアオブジェクト
	 */
	public function __construct($px){
		$this->px = $px;

		//  設定オブジェクトを読み込む
		$class_name = $this->px->load_px_plugin_class('/pxOAuthConsumer/conf.php');
		$this->conf = new $class_name($px);

		//  セッションを読み込む
		$this->session = $this->px->req()->get_session( $this->conf->get_session_key() );

		//  ライブラリのロード
		$provider_list = $this->conf->get_provider_list();
		foreach($provider_list as $provider_name){
			if($provider_name == 'twitter'){
				//  Twitterを使用できる設定の場合、
				//  TwitterOAuth を使用。
				require_once( $this->px->get_conf('paths.px_dir').'libs/twitteroauth/OAuth.php' );
				require_once( $this->px->get_conf('paths.px_dir').'libs/twitteroauth/twitteroauth.php' );
			}
		}

	}//__construct()

	/**
	 * ユーザーのステータスを調べる
	 */
	public function get_user_status($provider_name){
		if( $this->px->req()->get_param($this->conf->get_callback_param_key()) == $provider_name ){
			return 'callback';
		}
		if( is_array( $this->session[$provider_name]['profile'] ) ){
			return 'login';
		}
		return 'no_login';
	}//get_user_status()

	/**
	 * ユーザーのOAuthログイン状況を調べる
	 */
	public function is_login($provider_name){
		switch( $this->get_user_status($provider_name) ){
			case 'callback':
			case 'login':
				return true;
				break;
			case 'no_login':
				return false;
				break;
		}
		return false;
	}//is_login()


	/**
	 * ユーザー情報を取得する
	 */
	public function get_user_info($provider_name){
		return $this->session[$provider_name]['profile'];
	}

	/**
	 * セッションを保存する
	 */
	private function save_session(){
		return $this->px->req()->set_session($this->conf->get_session_key(), $this->session);
	}//save_session()

	/**
	 * コールバックURLを取得
	 */
	private function get_callback_url($provider_name){
		$rtn = '';
		$rtn .= 'http'.($this->px->req()->is_ssl()?'s':'').'://'.t::h($_SERVER['SERVER_NAME']);
		$rtn .= $this->px->theme()->href($this->px->req()->get_request_file_path());
		$rtn .= '?'.urlencode($this->conf->get_callback_param_key()).'='.urlencode($provider_name);
		return $rtn;
	}


	/**
	 * コールバックリクエストを受ける
	 */
	public function receive_callback(){
		$provider_name = $this->px->req()->get_param($this->conf->get_callback_param_key());
		if( !strlen($provider_name) ){
			return false;
		}
		$provider_conf = $this->conf->get_provider_info( $provider_name );

		if($provider_name == 'twitter'){
			if( $this->session[$provider_name]['oauth_token'] !== $this->px->req()->get_param('oauth_token') ){
				return false;
			}

			$TwitterOAuth = new TwitterOAuth( $provider_conf['consumer_key'], $provider_conf['consumer_secret'], $this->session[$provider_name]['oauth_token'], $this->session[$provider_name]['oauth_token_secret'] );
			$access_token = $TwitterOAuth->getAccessToken($this->px->req()->get_param('oauth_verifier'));

			if($TwitterOAuth->http_code != 200){
				return false;
			}

			$this->session['oauth_token']        = $access_token['oauth_token'];
			$this->session['oauth_token_secret'] = $access_token['oauth_token_secret'];



			//  ユーザー情報を取得
			$TwitterOAuth = new TwitterOAuth( $provider_conf['consumer_key'], $provider_conf['consumer_secret'], $this->session['oauth_token'], $this->session['oauth_token_secret'] );

			//  home_timelineの取得。TwitterからXML形式が返ってくる
			$response = $TwitterOAuth->OAuthRequest("http://api.twitter.com/1/account/verify_credentials.json","GET");

			// Twitterから返されたJSONをデコードする
			$results = json_decode($response);
			if( count($results->errors) ){
				return false;
			}

			$this->session[$provider_name]['profile'] = array();
			$this->session[$provider_name]['profile']['id']          = $results->id;
			$this->session[$provider_name]['profile']['screen_name'] = $results->screen_name;
			$this->session[$provider_name]['profile']['name']        = $results->name;
			$this->session[$provider_name]['profile']['description'] = $results->description;
			$this->session[$provider_name]['profile']['lang']        = $results->lang;

			$this->save_session();//セッションを保存

			return true;
		}// twitter

		return false;
	}//receive_callback()

	/**
	 * ログインURLを取得
	 */
	public function get_auth_url( $provider_name ){
		$provider_conf = $this->conf->get_provider_info( $provider_name );

		switch($provider_name){
			case 'twitter':
				$TwitterOAuth = new TwitterOAuth($provider_conf['consumer_key'], $provider_conf['consumer_secret']);
				$request_token = $TwitterOAuth->getRequestToken($this->get_callback_url($provider_name));

				$this->session[$provider_name]['oauth_token']        = $request_token['oauth_token'];
				$this->session[$provider_name]['oauth_token_secret'] = $request_token['oauth_token_secret'];
				$this->save_session();

				$auth_url = $TwitterOAuth->getAuthorizeURL($this->session[$provider_name]['oauth_token']);
				return $auth_url;

				break;
		}
		return false;
	}


	/**
	 * ログアウトする
	 */
	public function logout( $provider_name ){
		unset($this->session[$provider_name]);
		$this->save_session();
		return true;
	}

	/**
	 * すべてのプロバイダからログアウトする
	 */
	public function logout_all(){
		$this->session = null;
		$this->save_session();
		return true;
	}

}

?>