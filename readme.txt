/*
 * PxPlugin "pxOAuthConsumer" for Pickles Framework 1.x
 *--------------------------------------*/

Pickles Framework 1.x に組み込んで使用する
プラグインライブラリです。

PxFWに、外部のOAuthプロバイダーのアカウントでログインする
機能を追加します。

現在対応しているのは下記のプロバイダーです。

    - Twitter


OAuthプロバイダーアカウントでログインしても、
PxFWのユーザーとの紐付けはされませんので、
独自に実装する必要があります。



【インストール方法】

1. PxFW(Pickles Framework) をセットアップする。
2. PxFW の libs ディレクトリに、
   twitteroauthディレクトリをアップする。
3. PxFW の plugins ディレクトリに、
   pxOAuthConsumerディレクトリをアップする。
4. pxOAuthConsumer/conf.php を開き、
   OAuthプロバイダー毎の値を設定する。
5. _sampleContents/index.html のコードを参考に、
   PxFWのコンテンツを実装する。


        * Pickles Framework については、
          次のページから入手してください。
          https://github.com/tomk79/PxFW-1.x/tags


【ライブラリ・依存関係】

このプラグインは、ツイッターAPIとの交信に、
twitteroauth (https://github.com/abraham/twitteroauth)
を使用しています。(同梱)


