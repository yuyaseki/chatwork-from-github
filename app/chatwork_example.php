<?php
declare(strict_types = 1);

use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder) {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        "chatwork" => [
            // Chatwork APIを利用するためのtoken
            "token" => "zzzzzzzzzzzzzzzzzzz",
            // 主に通知を行うチャットワークスレッドのroom_id
            "room_id" => "1111111example",
            "to" => [
                // 以下，GitHubのユーザをChatworkのアカウントに紐付ける設定を行う
                // 
                // revewerやassigneeに追加されたときなどに利用される．
                // room_idが空文字の場合は，上記room_idのスレッドにメッセージが送信される．
                // room_idに値が入っている場合は，上記room_idのスレッドにメッセージが送信されるとともに，
                // 指定したスレッドにも追加で送信される．
                //
                // "[github username]" => [
                //     "room_id" => "[chatwork room id]",
                //     "account_id" => "[chatowrk account id]",
                //     "account_name" => "[chatwork account name]"
                // ]
                //
                // 例：
                "github-user" => [
                    "room_id" => "222222example",
                    "account_id" => "example",
                    "account_name" => "chatwork-user"
                ]
            ]
        ],
    ]);
};
