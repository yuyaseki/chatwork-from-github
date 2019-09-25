<?php

namespace App\Models;

class GitHubToChatwork {


    public static function getInfo($event, $data, $logger, $to) {

        $room_id_list = [];

        $message = "[hr]\n";
        $message = $message . "【" . $data["repository"]["name"] . "】\n";
        $message = $message . "Description: " . $data["repository"]["description"] . "\n\n";
        //RepositoryのURLを出す意味は無い気がするので一旦コメントアウト．
        //$message = $message . $data["repository"]["html_url"] . "\n\n";

        //pull_request
        if($event == "pull_request") {
            $action = $data["action"];
            if($action == "synchronize" || $action == "submitted" || $action == "labeled") {
                return true;
            }

            $message = self::getPullRequestMessage($message, $action, $data, $to, $logger);
            $room_id_list[] = self::getPullRequestTo($action, $data, $to);

        //pull_request_review
        } else if($event == "pull_request_review") {

            $action = $data["action"];
            if($action == "submitted") {
                return true;
            }

            $message = self::getPullRequestReviewMessage($message, $action, $data, $logger);

        //pull_request_review_comment
        } else if($event == "pull_request_review_comment") {

            $action = $data["action"];

            $message = self::getPullRequestReviewCommentMessage($message, $action, $data, $logger);

        //issue_comment
        } else if($event == "issue_comment") {

            $action = $data["action"];

            $message = $message . "[Issue Comment]\n"
                                . "Issue Comment " . $action . " " . $data["issue"]["user"]["login"] . "\n"
                                . "\n"
                                . "#" . $data["issue"]["number"] . " " . $data["issue"]["title"] . "\n"
                                . $data["issue"]["html_url"];

        //gollum
        } else if($event == "gollum") {

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
        } else if($event == "ping") {
            $message = "[hr]\n[Test送信]";

        // Other
        } else {
            $message = "";
        }

        return [
            "message" => $message,
            "room_id_list" => $room_id_list
        ];
    }

    /**
     * Pull Request
     *
     */
    private static function getPullRequestMessage($message, $action, $data, $to, $logger) {

        $merged = $data["pull_request"]["merged"];

        // action typeによりaction_messageを変更
        $action_message = "";
        if($action == "closed" && $merged) {
            $action_message = "closed with merged";
        } else if($action == "closed" && !$merged) {
            $action_message = "closed with unmerged commits";
        } else {
            $action_message = $action;
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

            if(!$is_team) {

                $requested_reviewer = $data["requested_reviewer"]["login"];
                if(array_key_exists($requested_reviewer, $to) &&
                   array_key_exists("account_id", $to[$requested_reviewer]) &&
                   array_key_exists("account_name", $to[$requested_reviewer])) {

                    $to_notation = "[To:" . $to[$requested_reviewer]["account_id"] . "] " . $to[$requested_reviewer]["account_name"] . "\n";
                } else {
                    throw new Exception("Chatwork Setting Invalid!");
                }
            } else {
                $requested_reviewer = $data["requested_team"]["name"];
                $to_notation = "";
            }
        }

        $assignee = "";
        if($action == "assigned" || $action == "unassigned") {
            $assignee = $data["assignee"]["login"];
            if(array_key_exists($assignee, $to)) {
                $to_notation = "[To:" . $to[$assignee]["account_id"] . "] " . $to[$assignee]["account_name"] . "\n";
            } else {
                $to_notation = "";
            }
        }

        $message = $message . "[qt][Pull Request][/qt]\n";

        $honorific = $is_team ? "チーム" : "さん";
        if($action == "review_requested") {
            $message = $message . $to_notation;
            $message = $message . $requested_reviewer . " " . $honorific . "がレビュアーに指定されました．";
        } else if($action == "review_request_removed") {
            $message = $message . $to_notation;
            $message = $message . $requested_reviewer . " " . $honorific . "がレビュアーから除外されました．";
        } else if($action == "assigned") {
            $message = $message . $to_notation;
            $message = $message . $assignee . " さんが責任者に指定されました．";
        } else if($action == "unassigned") {
            $message = $message . $to_notation;
            $message = $message . $assignee . " さんが責任者から除外されました．";
        } else {
            $message = $message . "Pull Request " . $action_message . " by " . $data["pull_request"]["user"]["login"];
        }

        $message = $message . "\n\n";
        $message = $message . "#" . $data["pull_request"]["number"] . " " . $data["pull_request"]["title"] . "\n"
                            . $data["pull_request"]["html_url"];

        if($action == "opened" && !is_null($data["pull_request"]["body"]) && $data["pull_request"]["body"] != "") {
            $message = $message . "\n[info]"
                                . $data["pull_request"]["body"] . "\n"
                                . "[/info]";
        }

        return $message;
    }

    /**
     * Pull Requestのレビュア，責任者への通知の場合のtoに指定するユーザのroom_idの取得．
     *
     */
    private static function getPullRequestTo($action, $data, $to) {

        $room_id = "";

        if($action == "review_requested" || $action == "review_request_removed") {

            $is_team = false;
            if(array_key_exists("requested_reviewer", $data)) {
                $is_team = false;
            } else if(array_key_exists("requested_team", $data)) {
                $is_team = true;
            }

            if(!$is_team) {
                $requested_reviewer = $data["requested_reviewer"]["login"];

                if(array_key_exists($requested_reviewer, $to) &&
                   array_key_exists("room_id", $to[$requested_reviewer])) {

                    $room_id = $to[$requested_reviewer]["room_id"];
                } else {
                    throw new Exception("Chatwork Setting Invalid!");
                }
            } else {
                $room_id = "";
            }
        }

        if($action == "assigned" || $action == "unassigned") {
            $assignee = $data["assignee"]["login"];

            if(array_key_exists($assignee, $to) && array_key_exists("room_id", $to[$assignee])) {
                $room_id = $to[$assignee]["room_id"];
            } else {
                $room_id = "";
            }
        }

        return $room_id;
    }

    /**
     * Pull Request Review
     *
     */
    private static function getPullRequestReviewMessage($message, $action, $data, $logger) {

        $message = $message . "[Pull Request Review]\n"
                            . "Pull Request Review " . $action . " by " . $data["review"]["user"]["login"] . "\n\n"
                            . "#" . $data["pull_request"]["number"] . " " . $data["pull_request"]["title"] . "\n"
                            . $data["pull_request"]["html_url"];

        return $message;
    }

    /**
     * Pull Request Review Comment
     *
     */
    private static function getPullRequestReviewCommentMessage($message, $action, $data, $logger) {

        $message = $message . "[Pull Request Review Comment]\n"
                            . "Pull Request Review Comment " . $action . " by " . $data["comment"]["user"]["login"] . "\n\n"
                            . "#" . $data["pull_request"]["number"] . " " . $data["pull_request"]["title"] . "\n"
                            . "Pull Request: " . $data["comment"]["pull_request"]["href"] . "\n";

        if($action != "deleted") {
            $message = $message . "Review Comment: " . $data["comment"]["html_url"];
        }

        $message = $message . "[info]"
                            . $data["comment"]["body"]
                            . "[/info]";

        return $message;
    }
}
