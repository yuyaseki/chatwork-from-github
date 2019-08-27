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

        $message = "[toall]\n\n";

        $message = $message . "【" . $data["repository"]["name"] . "】\n";
        $message = $message . "Description: " . $data["repository"]["description"] . "\n";
        $message = $message . $data["repository"]["html_url"] . "\n\n";

        //push
        if(trim($headers["X-Github-Event"]) == "push") {
            return true;
            /*
            $message = $message . "[Push]\n";
            $message = $message . "※リモートでのmergeなどもpush扱いです．\n";
            $message = $message . "Compareはブランチが削除されている場合無効です．\n";
            $message = $message . "[info]"
                                . "Push by " . $data["sender"]["login"] . ".\n"
                                . "\n"
                                . "git ref:   " . $data["ref"] . "\n"
                                . "Compare:   " . $data["compare"] . "\n"
                                . "[/info]";
            */
        //create
        } else if(trim($headers["X-Github-Event"]) == "create") {
            return true;
            /*
            $url = $data["repository"]["html_url"] . "/tree/" . $data["ref"];
            $message = $message . "[" . ($data["ref_type"] == "branch" ? "Branch" : "Tag") . " created]\n";
            $message = $message . "[info]"
                                . $data["ref"] . " was created by " . $data["sender"]["login"] . ".\n"
                                . "\n"
                                . "git ref:   " . $data["ref"] . "\n"
                                . $url . "\n"
                                . "[/info]";
            */
        //delete
        } else if(trim($headers["X-Github-Event"]) == "delete") {
            return true;
            /*
            $message = $message . "[" . ($data["ref_type"] == "branch" ? "Branch" : "Tag") . " deleted]\n";
            $message = $message . "[info]"
                                . $data["ref"] . " was deleted by " . $data["sender"]["login"] . ".\n"
                                . "[/info]";
            */
        //pull_request
        } else if(trim($headers["X-Github-Event"]) == "pull_request") {
            $merged = $data["pull_request"]["merged"];
            $action = "";
            if($data["action"] == "closed" && $merged) {
                $action = "closed with merged";
            } else if($data["action"] == "closed" && !$merged) {
                $action = "closed with unmerged commits";
            } else {
                $action = $data["action"];
            }
            $message = $message . "[Pull Request]\n";
            $message = $message . $data["action"] . "\n";
            $message = $message . "[info]"
                                . "Pull Request " . $action . " by " . $data["pull_request"]["user"]["login"] . ".\n"
                                . "\n"
                                . "Message: " . $data["pull_request"]["body"] . "\n"
                                . "\n"
                                . "#" . $data["pull_request"]["number"] . " " . $data["pull_request"]["title"] . "\n"
                                . $data["pull_request"]["html_url"] . "\n"
                                . "[/info]";
        //pull_request_review
        } else if(trim($headers["X-Github-Event"]) == "pull_request_review") {
            $message = $message . "[Pull Request Review]\n"
                                . $data["action"] . "\n"
                                . "[info]"
                                . "Pull Request Review " . $data["action"] . " by " . $data["review"]["user"]["login"] . "\n"
                                . "\n"
                                . $data["review"]["body"] . "\n"
                                . "\n"
                                . "#" . $data["pull_request"]["number"] . " " . $data["pull_request"]["title"] . "\n"
                                . $data["pull_request"]["html_url"] . "\n"
                                . "[/info]";
        //pull_request_review_comment
        } else if(trim($headers["X-Github-Event"]) == "pull_request_review_comment") {
            $message = $message . "[Pull Request Review Comment]\n"
                                . $data["action"] . "\n"
                                . "[info]"
                                . "Pull Request Review Comment " . $data["action"] . " by " . $data["comment"]["user"]["login"] . "\n"
                                . "\n"
                                . $data["comment"]["body"] . "\n"
                                . $data["comment"]["html_url"] . "\n"
                                . "\n"
                                . "#" . $data["pull_request"]["number"] . " " . $data["pull_request"]["title"] . "\n"
                                . $data["comment"]["html_url"] . "\n"
                                . "[/info]"
                                . "[code]"
                                . $data["comment"]["diff_hunk"]
                                . "[/code]";
        //gollum
        } else if(trim($headers["X-Github-Event"]) == "gollum") {
            $message = $message . "[Gollum]\n"
                                . "[info]"
                                . "Wiki page \"" . $data["pages"][0]["page_name"] . "\" " . $data["pages"][0]["action"] . " by " . $data["sender"]["login"] . "\n"
                                . "\n"
                                . "Page Name: " . $data["pages"][0]["page_name"]. "\n"
                                . "Summary: " . $data["pages"][0]["summary"]. "\n"
                                . "\n"
                                . $data["pages"][0]["html_url"] . "\n"
                                . "[/info]";
        //issue_comment
        } else if(trim($headers["X-Github-Event"]) == "issue_comment") {
            $message = $message . "[Issue Comment]\n"
                                . "[info]"
                                . "Issue Comment " . $data["action"] . " " . $data["issue"]["user"]["login"] . "\n"
                                . "\n"
                                . "#" . $data["issue"]["number"] . " " . $data["issue"]["title"] . "\n"
                                . $data["issue"]["html_url"] . "\n"
                                . "[/info]";
        //ping(test)
        } else if(trim($headers["X-Github-Event"]) == "ping") {
            $message = $message . "[Test送信]";
        //
        } else {
            $message = $message . "[" . trim($headers["X-Github-Event"]) . "]\n"
                                . "通知の必要はあるか．．．";
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
