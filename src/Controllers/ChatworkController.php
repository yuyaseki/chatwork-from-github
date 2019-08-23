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

        $message = "[toall]\n";
        $message = $message . "お試し中です\n\n";
        $message = $message . "push, create, delete, pull_request, pull_request_reviewは必要だと思われる詳細を表示しています．それ以外のEventについては，現在Event名のみ記載しています．今後変更の可能性あり．\n\n";

        //push
        if(trim($headers["X-Github-Event"]) == "push") {
            $message = $message . "[Push]\n";
            $message = $message . "[info]"
                                . "Push by " . $data["sender"]["login"] . ".\n"
                                . "git ref:   " . $data["ref"] . "\n"
                                . "Compare:   " . $data["compare"] . "\n"
                                . "[/info]";
        //create
        } else if(trim($headers["X-Github-Event"]) == "create") {
            $url = $data["repository"]["html_url"] . "/tree/" . $data["ref"];
            $message = $message . "[" . $data["ref_type"] == "branch" ? "Branch" : "Tag" . " was created]\n";
            $message = $message . "[info]"
                                . $data["ref"] . " was created by " . $data["sender"]["login"] . ".\n"
                                . "git ref:   " . $data["ref"] . "\n"
                                . "URL:   " . $url . "\n"
                                . "[/info]";
        //delete
        } else if(trim($headers["X-Github-Event"]) == "delete") {
            $message = $message . "[" . $data["ref_type"] == "branch" ? "Branch" : "Tag" . " was deleted]\n";
            $message = $message . "[info]"
                                . $data["ref"] . " was deleted by " . $data["sender"]["login"] . ".\n"
                                . "[/info]";
        //pull_request
        } else if(trim($headers["X-Github-Event"]) == "pull_request") {
            $merged = (boolean)$data["pull_request"]["_links"]["merged"];
            $action = "";
            if($data["action"] == "closed" && $merged) {
                $action = "closed with merged";
            } else if($data["action"] == "closed" && !$merged) {
                $action = "closed with unmerged commits";
            } else {
                $action = $data["action"];
            }
            $message = $message . "[Pull Request]\n";
            $message = $message . "[info]"
                                . "Pull Request " . $action . " by " . $data["pull_request"]["user"]["login"] . ".\n"
                                . "\n"
                                . $data["pull_request"]["body"] . "\n"
                                . "\n"
                                . "#" . $data["pull_request"]["number"] . " " . $data["pull_request"]["title"] . "\n"
                                . $data["pull_request"]["html_url"] . "\n"
                                . "[/info]";
        //pull_request_review
        } else if(trim($headers["X-Github-Event"]) == "pull_request_review") {
            $message = $message . "[Pull Request Review]\n"
                                . "[info]"
                                . "Pull Request Review " . $data["action"] . " by " . $data["review"]["user"]["login"] . "\n"
                                . "\n"
                                . $data["review"]["body"] . "\n"
                                . "\n"
                                . "#" . $data["pull_request"]["number"] . " " . $data["pull_request"]["title"] . "\n"
                                . $data["pull_request"]["html_url"] . "\n"
                                . "[/info]";
        //ping(test)
        } else if(trim($headers["X-Github-Event"]) == "ping") {
            $message = $message . "[Test送信]";
        } else {
            $message = $message . "[" . trim($headers["X-Github-Event"]) . "]";
        }

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
