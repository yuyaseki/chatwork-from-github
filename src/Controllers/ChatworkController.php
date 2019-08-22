<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Slim\Http\Request;
use Slim\Http\Response;

class ChatworkController {

    public function index(ServerRequestInterface $request, ResponseInterface $response) {

        global $app;
        $container = $app->getContainer();
        $settings = $container->get('settings')['logger'];
        $formatter = new \Monolog\Formatter\LineFormatter(null, null, true, true);
        $stream = new \Monolog\Handler\RotatingFileHandler($settings["path"], 30, $settings["level"]);
        $stream->setFormatter($formatter);
        $logger = new \Monolog\Logger($settings["name"]);
        $logger->pushHandler($stream);

        $logger->addInfo("***************************************");
        $logger->addInfo("***************************************");

        $tmp = $request->getHeaders();
        $headers = [];
        foreach ($tmp as $name => $values) {
            $logger->addInfo($name . ": " . implode(", ", $values));
            $headers[$name] = implode(", ", $values);
        }

	    $arr = json_decode($request->getBody(), true);
        ob_start();
	    var_dump($arr);
	    $data = ob_get_contents();
	    ob_end_clean();
        $logger->addInfo($data);

        self::pushChatwork($headers, $arr, $logger);

        $response->getBody()->write(json_encode(["success"]));

        return $response;
    }

    private static function pushChatwork($headers, $data, $logger) {

        global $app;
        $container = $app->getContainer();
        $settings = $container->get('settings')['chatwork'];

        $token = $settings["token"];
        $room_id = $settings["room_id"];

        $message = "お試し中です\n\n";
        if(trim($headers["X-Github-Event"]) == "push") {
        } else if(trim($headers["X-Github-Event"]) == "create") {
        } else if(trim($headers["X-Github-Event"]) == "delete") {
        } else if(trim($headers["X-Github-Event"]) == "pull_request") {
        } else if(trim($headers["X-Github-Event"]) == "pull_request_review") {
        } else if(trim($headers["X-Github-Event"]) == "pull_request_review_comment") {
        } else if(trim($headers["X-Github-Event"]) == "ping") {
            $logger->addInfo("ping");
        } else {
            return true;
        }

        ob_start();
	    var_dump($data);
	    $message = $message . ob_get_contents();
	    ob_end_clean();

        $query = http_build_query([
            "body" => $message,
            "self_unread" => 1
        ]);

        $header = [
            'Content-Type: application/x-www-form-urlencoded',
            'X-ChatworkToken: ' . $token,
            'Content-Length: ' . strlen($query)
        ];

        $url = "https://api.chatwork.com/v2/rooms/" . $room_id . "/messages";

        $options = [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_HEADER          => true,
            CURLOPT_HTTPHEADER      => $header,
            CURLOPT_POSTFIELDS      => $query
        ];

        $result = self::execute($url, $options);

        ob_start();
	    var_dump($result["header"]);
	    $header = ob_get_contents();
	    ob_end_clean();
        ob_start();
	    var_dump($result["body"]);
	    $body = ob_get_contents();
	    ob_end_clean();
        $logger->addInfo($body);
    }

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
