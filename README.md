<h1 align="center">GitHubイベントのChatwork通知用プログラム</h1>

# このプロジェクトについて

- このプロジェクトはGitHubにWebhookしてChatworkに通知を送るための処理をPHPフレームワーク，Slim/Skeletonで実装したものです．
- 配布用に作っているプログラムではないため，保守性の高いソースではないですが，問題なく動作することは確認済みです．
- 自由に利用してください．

# LICENSE

- This software is released under the MIT License, see [LICENSE.md](LICENSE.md).

# 環境情報

- Slim4が動かせる環境
- [direnv](https://github.com/direnv/direnv)がインストールされていること．

# 環境構築

~~~
git clone https://github.com/yuyaseki/chatwork-from-github.git

composer install

cp .env.example .env
cp app/chatwork_example.php app/chatwork.php

chmod 777 logs
~~~

# 環境設定

- .env内の設定を適切に変更する．

- app/chatwork.phpの設定を適切に変更する．

# GitHubの設定

- GitHubの通知が必要なリポジトリのSettingsからWebhookを設定．

以上の設定で通知がChatworkに届くようになるはずです．

# その他

※Slimについての説明は以下をご確認ください．

# Slim Framework 4 Skeleton Application

Use this skeleton application to quickly setup and start working on a new Slim Framework 4 application. This application uses the latest Slim 4 with Slim PSR-7 implementation and PHP-DI container implementation. It also uses the Monolog logger.

This skeleton application was built for Composer. This makes setting up a new Slim Framework application quick and easy.

## Install the Application

Run this command from the directory in which you want to install your new Slim Framework application.

```bash
composer create-project slim/slim-skeleton [my-app-name]
```

Replace `[my-app-name]` with the desired directory name for your new application. You'll want to:

* Point your virtual host document root to your new application's `public/` directory.
* Ensure `logs/` is web writable.

To run the application in development, you can run these commands 

```bash
cd [my-app-name]
composer start
```

Or you can use `docker-compose` to run the app with `docker`, so you can run these commands:
```bash
cd [my-app-name]
docker-compose up -d
```
After that, open `http://0.0.0.0:8080` in your browser.

Run this command in the application directory to run the test suite

```bash
composer test
```

That's it! Now go build something cool.
