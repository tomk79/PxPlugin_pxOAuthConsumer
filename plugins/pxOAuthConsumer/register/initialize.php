<?php

/**
 * PX Plugin "pxOAuthConsumer"
 */
class pxplugin_pxOAuthConsumer_register_initialize{
	private $px;
	private $errors = array();
	private $logs = array();

	/**
	 * コンストラクタ
	 * @param $px = PxFWコアオブジェクト
	 */
	public function __construct($px){
		$this->px = $px;
	}

	/**
	 * トリガーメソッド
	 * PxFWはインスタンスを作成した後、このメソッドをキックします。
	 * @params int $behavior: 振る舞い。0(既定値)=SQLを実行する|1=SQL文全体を配列として返す|2=SQL全体を文字列として返す
	 * @return 正常終了した場合に true , 異常が発生した場合に false を返します。
	 */
	public function execute($behavior=0){
		$behavior = intval($behavior);
		$sql_srcs = array();

		//  エラーが発生した場合は、
		//  エラーメッセージを出力し、falseを返す。
		/*
		if( $error ){
			$this->error_log('エラーが発生したため、処理を中止しました。',__LINE__);
			$this->log('エラーが発生したため、処理を中止しました。');
			return false;
		}
		*/

		//  テーブル sample_table を作成
		$sql_srcs['px_oauth_consumer_user_relay'] = array();
		ob_start(); ?>
<?php if( $this->px->get_conf('dbms.dbms') == 'postgresql' ){ ?>
CREATE TABLE :D:table_name(
    user_id    VARCHAR NOT NULL,
    oauth_user_id    VARCHAR NOT NULL UNIQUE,
    oauth_provider_name    VARCHAR NOT NULL,
    login_date    TIMESTAMP DEFAULT 'NOW',
    create_date    TIMESTAMP DEFAULT 'NOW',
    delete_date    TIMESTAMP DEFAULT 'NOW',
    delete_flg    INT2 NOT NULL DEFAULT '0'
);
<?php }else{ ?>
CREATE TABLE :D:table_name(
    user_id    VARCHAR(64) NOT NULL,
    oauth_user_id    VARCHAR(64) NOT NULL UNIQUE,
    oauth_provider_name    VARCHAR(64) NOT NULL,
    login_date    DATETIME DEFAULT NULL,
    create_date    DATETIME DEFAULT NULL,
    delete_date    DATETIME DEFAULT NULL,
    delete_flg    INT(1) NOT NULL DEFAULT '0'
);
<?php } ?>
<?php
		array_push( $sql_srcs['px_oauth_consumer_user_relay'], @ob_get_clean() );


		if( !$behavior ){
			//  トランザクション：スタート
			$this->px->dbh()->start_transaction();
		}

		$sqls = array();
		foreach( $sql_srcs as $table_name=>$sql_row ){
			foreach( $sql_row as $sql_content ){
				$bind_data = array(
					'table_name'=>$this->px->get_conf('dbms.prefix').'_'.$table_name,
				);
				$sql_final = $this->px->dbh()->bind( $sql_content , $bind_data );
				if( !strlen( $sql_final ) ){ continue; }

				if( !$behavior ){
					if( !$this->px->dbh()->send_query( $sql_final ) ){
						$this->px->error()->error_log('database query error ['.$sql_final.']');
						$this->log('[ERROR] database query error. (see error log)',__LINE__);
						$this->error_log('database query error ['.$sql_final.']',__LINE__);

						//トランザクション：ロールバック
						$this->px->dbh()->rollback();
						return false;
					}else{
						$this->log('database query done.  ['.$sql_final.']',__LINE__);
					}
				}else{
					array_push( $sqls , $sql_final );
				}
				unset($sql_final);
			}
		}

		foreach( $sqls as $sql ){
		}

		if( $behavior === 1 ){
			return $sqls;
		}
		if( $behavior === 2 ){
			return implode( "\r\n\r\n\r\n", $sqls );
		}

		//  トランザクション：コミット
		$this->px->dbh()->commit();
		return true;
	}

	/**
	 * エラー取得メソッド
	 * PxFWはinitialize処理が終了した後(=execute()がreturnした後)、
	 * このメソッドを通じてエラー内容を受け取ります。
	 * @return 配列。配列の要素は、message, file, line の3つを持った連想配列。
	 */
	public function get_errors(){
		return $this->errors;
	}

	/**
	 * 内部エラー発行メソッド
	 * 本オブジェクト内部で発生したエラーを受け取り、メンバー変数に記憶します。
	 * ここで記憶したエラー情報は、最終的に get_errors() により引き出されます。
	 */
	private function error_log( $error_message , $line ){
		array_push( $this->errors, array(
			'message'=>$error_message ,
			'file'=>__FILE__ ,
			'line'=>$line ,
		) );
		return true;
	}

	/**
	 * ログ取得メソッド
	 * PxFWはinitialize処理が終了した後(=execute()がreturnした後)、
	 * このメソッドを通じて実行された処理の内容を受け取ります。
	 * @return 配列。
	 */
	public function get_logs(){
		return $this->logs;
	}

	/**
	 * 内部ログ記録メソッド
	 * 本オブジェクト内部で処理した内容をテキストで受け取り、メンバー変数に記憶します。
	 * ここで記憶した情報は、最終的に get_logs() により引き出されます。
	 */
	private function log( $message ){
		array_push( $this->logs, $message );
		return true;
	}

}

?>