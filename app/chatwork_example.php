<?php
declare(strict_types = 1);

use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder) {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        "chatwork" => [
            "token" => getenv("CHATWORK_TOKEN"),
            "room_id" => getenv("CHATWORK_ROOM_ID"),
            "to" => [
                // 以下の[]の部分を変更する
                // "[github username]" => [
                //     "chatwork_account_id" => "[chatowrk account id]",
                //     "chatwork_account_name" => "[chatwork account name]"
                // ]
            ]
        ],
    ]);
};
