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
        $chatwork = $container->get('chatwork');

        $token = $chatwork["token"];
        $room_id = $chatwork["room_id"];

        $message = $message . "【" . $data["repository"]["name"] . "】\n";
        $message = $message . "Description: " . $data["repository"]["description"] . "\n";
        $message = $message . $data["repository"]["html_url"] . "\n\n";

        //pull_request
        if(trim($headers["X-Github-Event"]) == "pull_request") {
            $message = self::getPullRequestMessage($message, $data, $chatwork["to"], $logger);
            $logger->info($message);

        //pull_request_review
        } else if(trim($headers["X-Github-Event"]) == "pull_request_review") {
            $message = self::getPullRequestReviewMessage($message, $data, $logger);

        //pull_request_review_comment
        } else if(trim($headers["X-Github-Event"]) == "pull_request_review_comment") {

            $message = self::getPullRequestReviewCommentMessage($message, $data, $logger);

        //issue_comment
        } else if(trim($headers["X-Github-Event"]) == "issue_comment") {
            $message = $message . "[Issue Comment]\n"
                                . "Issue Comment " . $data["action"] . " " . $data["issue"]["user"]["login"] . "\n"
                                . "\n"
                                . "#" . $data["issue"]["number"] . " " . $data["issue"]["title"] . "\n"
                                . $data["issue"]["html_url"];

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

        // ping(test)
        } else if(trim($headers["X-Github-Event"]) == "ping") {
            $message = $message . "[Test送信]";

        // Other
        } else {
            return true;
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
    }

    /**
     * Pull Request
     *
     */
    private static function getPullRequestMessage($message, $data, $to, $logger) {

        $merged = $data["pull_request"]["merged"];

        $action = "";
        if($data["action"] == "closed" && $merged) {
            $action = "closed with merged";
        } else if($data["action"] == "closed" && !$merged) {
            $action = "closed with unmerged commits";
        } else {
            $action = $data["action"];
        }

        $is_team = false;
        $requested_reviewer = "";
        $to_notation = "";
        if($action == "review_requested" || $action == "review_request_removed") {
            if(array_key_exists("requested_reviewer", $data)) {
                $is_team = false;
            } else if(array_key_exists("requested_team", $data)) {
                $is_team = true;
            }
            if(!$is_team && array_key_exists($data["requested_reviewer"]["login"], $to)) {
                $requested_reviewer = $data["requested_reviewer"]["login"];
                $to_notation = "[To:" . $to[$requested_reviewer]["chatwork_account_id"] . "] " . $to[$requested_reviewer]["chatwork_account_name"] . "\n";
            } else if($is_team) {
                $requested_reviewer = $data["requested_team"]["name"];
                $to_notation = "";
            } else {
                $to_notation = "";
            }
        }

        $assignee = "";
        if($action == "assigned" || $action == "unassigned") {
            $assignee = $data["assignee"]["login"];
            if(array_key_exists($assignee, $to)) {
                $to_notation = "[To:" . $to[$assignee]["chatwork_account_id"] . "] " . $to[$assignee]["chatwork_account_name"] . "\n";
            } else {
                $to_notation = "";
            }
        }

        $message = $message . "[Pull Request]\n";

        if($action == "review_requested") {
            $message = $message . $to_notation;
            $message = $message . $requested_reviewer . " " . ($is_team ? "チーム" : "さん") . "がレビュアーに指定されました．";
        } else if($action == "review_request_removed") {
            $message = $message . $to_notation;
            $message = $message . $requested_reviewer . " " . ($is_team ? "チーム" : "さん") . "がレビュアーから除外されました．";
        } else if($action == "assigned") {
            $message = $message . $to_notation;
            $message = $message . $assignee . " さんが責任者に指名されました．";
        } else if($action == "unassigned") {
            $message = $message . $to_notation;
            $message = $message . $assignee . " さんが責任者から除外されました．";
        } else {
            $message = $message . "Pull Request " . $action . " by " . $data["pull_request"]["user"]["login"];
        }

        $message = $message . "\n\n";
        $message = $message . "#" . $data["pull_request"]["number"] . " " . $data["pull_request"]["title"] . "\n"
                            . $data["pull_request"]["html_url"];

        if($action == "opened") {
            $message = $message . "\n
                                . [info]"
                                . "Message: " . $data["pull_request"]["body"] . "\n"
                                . "[/info]";
        }

        return $message;
    }

    /**
     * Pull Request Review
     *
     */
    private static function getPullRequestReviewMessage($message, $data, $logger) {

            $message = $message . "[Pull Request Review]\n"
                                . "Pull Request Review " . $data["action"] . " by " . $data["review"]["user"]["login"] . "\n\n"
                                . "#" . $data["pull_request"]["number"] . " " . $data["pull_request"]["title"] . "\n"
                                . $data["pull_request"]["html_url"];

        return $message;
    }

    /**
     * Pull Request Review Comment
     *
     */
    private static function getPullRequestReviewCommentMessage($message, $data, $logger) {

        $message = $message . "[Pull Request Review Comment]\n"
                            . "Pull Request Review Comment " . $data["action"] . " by " . $data["comment"]["user"]["login"] . "\n\n"
                            . "#" . $data["pull_request"]["number"] . " " . $data["pull_request"]["title"] . "\n"
                            . "Pull Request: " . $data["comment"]["pull_request_url"] . "\n";

        if($data["action"] != "deleted") {
            $message = $message . "Review Comment: " . $data["comment"]["html_url"];
        }

        $message = $message . "[info]"
                            . $data["comment"]["body"]
                            . "[/info]";

        return $message;
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
