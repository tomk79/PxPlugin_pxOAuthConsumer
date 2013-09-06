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
		$this->px->user()->logout();
		return true;
	}



	/**
	 * ログインしているPXユーザーと紐付ける
	 */
	public function relay_to_px_user( $provider_name ){
		if( !$this->px->user()->is_login() ){
			//  ログインしてない人はできません。
			return false;
		}

		if( !$this->is_login($provider_name) ){
			//  ログインしてない人はできません。
			return false;
		}

		//  予め紐付けデータがあるか調べる。
		$relay_data = $this->get_px_user_relay_info( $provider_name );
		if(is_array($relay_data)){
			//  すでに紐付けされている場合
			if( $relay_data['user_id'] != $this->px->user()->get_login_user_id() ){
				//  違うユーザーに紐付けられている。
				return false;
			}
			//  ログイン日時を更新
			if(!$this->update_login_date($provider_name)){
				return false;
			}
			return true;
		}else{
			//  はじめて紐付ける場合
			$result = $this->insert_relay_info( $provider_name );
			return $result;
		}

		return false;
	}//relay_to_px_user()

	/**
	 * ログインしているOAuthユーザーと紐づいたPXユーザーとしてログインする
	 */
	public function login_px_user( $provider_name ){
		if( !$this->is_login($provider_name) ){
			//  ログインしてない人はできません。
			return false;
		}

		//  予め紐付けデータがあるか調べる。
		$relay_data = $this->get_px_user_relay_info( $provider_name );

		if( $this->px->user()->is_login() ){
			//  すでにログインしていた人は、
			//  リレーテーブルの内容と照合し、正しいか調べる。
			if( $this->px->user()->get_login_user_id() != $relay_data['user_id'] ){
				return false;
			}
			$this->update_login_date($provider_name);
			return true;
		}

		$oauth_user_info = $this->get_user_info( $provider_name );

		//  ログイン情報をセッションに入れる
		$this->px->req()->set_session('USER_ID',$relay_data['user_id']);
		$this->px->req()->set_session('USER_EXPIRE',time()+1800);
		$this->px->user()->update_login_status(null,null);

		if( !$this->px->user()->is_login() ){
			return false;
		}
		$this->update_login_date($provider_name);
		return true;
	}//login_px_user()

	/**
	 * ログインしているOAuthユーザーの情報から、PXユーザー情報を自動的に登録する
	 */
	public function auto_create_px_user( $provider_name ){
		if( !$this->is_login($provider_name) ){
			//  ログインしてない人はできません。
			return false;
		}

		$oauth_user_info = $this->get_user_info( $provider_name );

		$class_name_dao_user = $this->px->load_px_class( '/daos/user.php' );
		if( !$class_name_dao_user ){
			return false;
		}
		$dao_user = new $class_name_dao_user( $this->px );

		$user_info = array(
			'user_account'=>$oauth_user_info['screen_name'],
			'user_pw'=>$this->px->user()->crypt_user_password( uniqid() ),
			'user_name'=>$oauth_user_info['name'],
			'user_email'=>null,
			'auth_level'=>1,
		);
		if( !$dao_user->create_user($user_info) ){
			return false;
		}

		//  作成したログイン情報をセッションに入れ、ログインする。
		$user_id = $dao_user->get_last_insert_user_id();
		$this->px->req()->set_session('USER_ID',$user_id);
		$this->px->req()->set_session('USER_EXPIRE',time()+1800);
		$this->px->user()->update_login_status(null,null);

		if( !$this->px->user()->is_login() ){
			return false;
		}

		//  ログインしたユーザーと紐付ける
		if( !$this->relay_to_px_user($provider_name) ){
			return false;
		}
		return true;
	}//auto_create_px_user()





	/**
	 * OAuthログイン情報から、PXユーザー情報を取得する
	 */
	private function get_px_user_relay_info($provider_name){
		$oauth_user_info = $this->get_user_info( $provider_name );

		//  予め紐付けデータがあるか調べる。
		ob_start(); ?>
SELECT * FROM :D:table_name
WHERE
    oauth_user_id = :S:oauth_user_id
    AND oauth_provider_name = :S:oauth_provider_name
    AND delete_flg = 0
;
<?php
		$sql = ob_get_clean();

		$bind_data = array(
			'table_name'=>$this->px->get_conf('dbms.prefix').'_px_oauth_consumer_user_relay',
			'oauth_user_id'=>$oauth_user_info['id'],
			'oauth_provider_name'=>$provider_name,
		);

		$sql = $this->px->dbh()->bind( $sql , $bind_data );
		$res = $this->px->dbh()->send_query( $sql );
		$value = $this->px->dbh()->get_results();
		$rtn = $value[0];
		return $rtn;
	}//get_px_user_relay_info()


	/**
	 * リレー情報を追加する。
	 */
	private function insert_relay_info($provider_name){
		if( !$this->px->user()->is_login() ){
			//  ログインしてない人はできません。
			return false;
		}
		if( !$this->is_login($provider_name) ){
			//  ログインしてない人はできません。
			return false;
		}

		$oauth_user_info = $this->get_user_info( $provider_name );

		ob_start(); ?>
INSERT INTO :D:table_name(
    user_id,
    oauth_user_id,
    oauth_provider_name,
    login_date,
    create_date,
    delete_flg
) VALUES (
    :S:user_id,
    :S:oauth_user_id,
    :S:oauth_provider_name,
    :S:now,
    :S:now,
    :N:delete_flg
);
<?php
		$sql = ob_get_clean();

		$bind_data = array(
			'table_name'=>$this->px->get_conf('dbms.prefix').'_px_oauth_consumer_user_relay',
		    'user_id'=>$this->px->user()->get_login_user_id(),
		    'oauth_user_id'=>$oauth_user_info['id'],
		    'oauth_provider_name'=>$provider_name,
		    'now'=>$this->px->dbh()->int2datetime( time() ),
		    'delete_flg'=>0
		);

		$sql = $this->px->dbh()->bind( $sql , $bind_data );
		$res = $this->px->dbh()->send_query( $sql );
		if( !$res ){
			return false;
		}
		$value = $this->px->dbh()->get_results();
		$this->px->dbh()->commit();
		return true;
	}//insert_relay_info()


	/**
	 * リレー情報上のログイン日時を更新する。
	 */
	private function update_login_date($provider_name){
		if( !$this->px->user()->is_login() ){
			//  ログインしてない人はできません。
			return false;
		}
		if( !$this->is_login($provider_name) ){
			//  ログインしてない人はできません。
			return false;
		}

		$oauth_user_info = $this->get_user_info( $provider_name );

		ob_start(); ?>
UPDATE :D:table_name 
SET
    login_date = :S:now 
WHERE
    user_id = :S:user_id
    AND oauth_user_id = :S:oauth_user_id
    AND oauth_provider_name = :S:oauth_provider_name
    AND delete_flg = 0
;
<?php
		$sql = ob_get_clean();

		$bind_data = array(
			'table_name'=>$this->px->get_conf('dbms.prefix').'_px_oauth_consumer_user_relay',
		    'user_id'=>$this->px->user()->get_login_user_id(),
		    'oauth_user_id'=>$oauth_user_info['id'],
		    'oauth_provider_name'=>$provider_name,
		    'now'=>$this->px->dbh()->int2datetime( time() ),
		    'delete_flg'=>0
		);

		$sql = $this->px->dbh()->bind( $sql , $bind_data );
		$res = $this->px->dbh()->send_query( $sql );
		if( !$res ){
			return false;
		}
		$value = $this->px->dbh()->get_results();
		$this->px->dbh()->commit();
		return true;
	}//update_login_date()


}

?>