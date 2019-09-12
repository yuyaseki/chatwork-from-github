<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Slim\Http\Request;
use Slim\Http\Response;

use App\Models\GitHubToChatwork;

class ChatworkController {


    /**
     * Main Action.
     *
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response) {

        global $app;
        $container = $app->getContainer();

        // Logの設定
        $settings = $container->get('settings')['logger'];
        $formatter = new \Monolog\Formatter\LineFormatter(null, null, true, true);
        $stream = new \Monolog\Handler\RotatingFileHandler($settings["path"], 30, $settings["level"]);
        $stream->setFormatter($formatter);
        $logger = new \Monolog\Logger($settings["name"]);
        $logger->pushHandler($stream);

        // Chatworkの設定
        $chatwork = $container->get('chatwork');

        $logger->addInfo("******************************************************************************************************************");

        $header = $request->getHeaders();
	    $body = json_decode($request->getBody(), true);

        $headers = [];
        $event = "";
        foreach ($header as $name => $value) {
            $headers[$name] = trim(implode(", ", $value));
            if($name == "X-Github-Event") {
                $event = trim(implode(", ", $value));
            }
        }

        ob_start();
	    var_dump($headers);
	    $data = ob_get_contents();
	    ob_end_clean();
        $logger->addInfo($data);

        ob_start();
	    var_dump($body);
	    $data = ob_get_contents();
	    ob_end_clean();
        $logger->addInfo($data);


        self::pushChatwork($event, $body, $logger, $chatwork);

        $response->getBody()->write(json_encode(["success"]));

        return $response;
    }

    /**
     * GitHubのEventから必要情報を読み取り，Chatworkに通知する
     *
     */
    private static function pushChatwork($event, $data, $logger, $chatwork) {

        $token = $chatwork["token"];
        $room_id_list = [];
        $room_id_list[] = $chatwork["room_id"];


        $info = GitHubToChatwork::getInfo($event, $data, $logger, $chatwork["to"]);
        $message = $info["message"];
        $room_id_list = array_merge($room_id_list, $info["room_id_list"]);

        if($message == "") return true;

        self::executeApi($logger, $token, $room_id_list, $message);
    }

    /**
     * Chatwork APIの実行
     *
     */
    private static function executeApi($logger, $token, $room_id_list, $message) {

        $query = http_build_query([
            "body" => $message,
            "self_unread" => 1
        ]);

        $header = [
            'Content-Type: application/x-www-form-urlencoded',
            'X-ChatworkToken: ' . $token,
            'Content-Length: ' . strlen($query)
        ];

        $options = [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_HEADER          => true,
            CURLOPT_HTTPHEADER      => $header,
            CURLOPT_POSTFIELDS      => $query
        ];

        foreach($room_id_list as $room_id) {
            if($room_id == "") continue;
            $url = "https://api.chatwork.com/v2/rooms/" . $room_id . "/messages";
            $result = self::execute($url, $options);
        }
    }

    /**
     * Execute
     *
     */
    private static function execute($url, $options) {

        try {
            $ch = curl_init($url);

            $options[CURLOPT_HEADER] = true;

            curl_setopt_array($ch, $options);

            $result = curl_exec($ch);

            $info = curl_getinfo($ch);

            $header = substr($result, 0, $info["header_size"]);
            $body = substr($result, $info["header_size"]);

            $body = json_decode($body, true);
        } catch(\Exception $e) {
            $body = [];
            $body["status"] = 700;
            $header = [];
        } finally {
            curl_close($ch);
        }
        return [
            "body" => $body,
            "header" => $header
        ];
    }
}
