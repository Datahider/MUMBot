<?php

namespace losthost\MUMBot;

use losthost\DB\DBView;
use losthost\DB\DBValue;
use losthost\DB\DB;
use losthost\SimpleAI\SimpleAIAgent;
use losthost\MUMBot\Data\MessageQueue;
use losthost\BackgroundProcess\BackgroundProcess;

class Dispatcher {
    
    const OPT_WAIT_USER = 300;
    
    public function dispatch() {
        $view = $this->getUserIdsView();
        
        while ($view->next()) {
            if (random_int(1, 1) <> 1) {
                continue;
            }
            
            if ($this->getLock("$view->user_id")) {
                $this->releaseLock("$view->user_id");
                BackgroundProcess::create(<<<'PHP'
                    <?php
                    require 'vendor/autoload.php';
                    require 'etc/config.php';
                    losthost\DB\DB::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PREF);
                    (new losthost\MUMBot\Dispatcher())->userHandler(%d);
                    PHP)->run($view->user_id);
            }
        }
    }
    
    public function userHandler(int $user_id) {
        error_log("userHandler started for user_id=$user_id");
        if (!$this->getLock("$user_id")) {
            error_log("Can't obtain lock in userHandler for $user_id");
            return;
        }
        $this->userLoop($user_id);
    }
    
    protected function userLoop(int $user_id) {
    
        error_log("userLoop started for user_id=$user_id");
        $time = time();
        while (time()-$time <= static::OPT_WAIT_USER) {
            if ($this->processUserMessages($user_id)) {
                $time = time();
            }
            sleep(10);
        }
    }
    
    protected function processUserMessages($user_id) {
        error_log("processUserMessages started for user_id=$user_id");
        $sql = <<<FIN
            SELECT id, sender_id AS user_id, content, type, received
            FROM [messagequeue]
            WHERE sender_id = chat_id AND sender_id = ? AND answer IS NULL
            ORDER BY received
            FIN;
        $message_data = new DBView($sql, [$user_id]);
        error_log("Got message_data for user_id=$user_id");
        
        $got_messages = false;
        
        while ($message_data->next()) {
            error_log("Got message id=$message_data->id");
            $got_messages = true;

            $message = new MessageQueue(['id' => $message_data->id]);
            $message->is_typing = 1;
            $message->isModified() && $message->write();

            $answer = SimpleAIAgent::build(DEEPSEEK_API_KEY)
                ->setTimeout(60)    
                ->setTemperature(1.4)
                ->setUserId("$user_id")
                ->setPrompt(USER_PROMPT)
                ->ask($message_data->content);

            $message->answer = $answer;
            $message->isModified() && $message->write();
        }
        
        return $got_messages;
    }
        
    protected function getUserIdsView() : DBView {
        
        $sql = <<<FIN
                SELECT DISTINCT sender_id AS user_id FROM [messagequeue]
                WHERE sender_id = chat_id
                FIN;
        $view = new DBView($sql);
        
        return $view;
    }

    protected function getLock(string $name) : bool {
        return (bool)(new DBValue("SELECT GET_LOCK(?, 0) AS locked", [$name]))->locked;
    }

    protected function releaseLock(string $name) : void {
        new DBValue("SELECT RELEASE_LOCK(?)", [$name]);
    }
    
}
