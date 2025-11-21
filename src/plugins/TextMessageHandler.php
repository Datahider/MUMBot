<?php declare(strict_types=1);

namespace MadelinePlugin;

use danog\MadelineProto\EventHandler\Attributes\Cron;
use danog\MadelineProto\EventHandler\Filter\FilterText;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;
use danog\MadelineProto\EventHandler\Filter\FilterRegex;
use danog\MadelineProto\PluginEventHandler;
use losthost\MUMBot\Data\UserSettings;
use danog\MadelineProto\EventHandler\Filter\FilterMedia;
use danog\MadelineProto\EventHandler\Filter\Media\FilterVoice;
use danog\MadelineProto\EventHandler\Media\Voice;
use losthost\YandexAI\YandexSpeachKitGateway;
use function Amp\File\read;
use losthost\DB\DB;
use losthost\DB\DBView;
use losthost\DB\DBValue;
use losthost\SimpleAI\SimpleAIAgent;
use danog\MadelineProto\ParseMode;

use losthost\MUMBot\Data\MessageQueue;

use losthost\ReflexA\ReflexA;

/**
 * Plugin event handler class.
 *
 * All properties returned by __sleep are automatically stored in the database.
 */
class TextMessageHandler extends PluginEventHandler
{
 
    const OPT_WAIT_USER = 300;
    const OPT_PROBABILITY = 1;
    
    protected array $ai_busy = [];
    protected array $message_queue = [];
    /**
     * Initialization logic.
     */
    public function onStart(): void
    {
        $this->logger("Text message handler started.");
        $this->sendMessageToAdmins("Text message handler started.");
    }

    /**
     * Plugins may be enabled or disabled at startup by returning true or false from this function.
     */
    public function isPluginEnabled(): bool
    {
        return true;
    }

    #[Cron(period: 5.0)]
    public function setTypingStatus() {
        $typing_user_ids = $this->getTypingIds();
        
        while ($typing_user_ids->next()) {
            $this->messages->setTyping(
                    action: ['_' => 'sendMessageTypingAction'], 
                    peer: $typing_user_ids->user_id);
        }
    }

    #[Cron(period: 10.0)]
    public function sendAnswered() {
        $answered = $this->getAnsweredMessages();
        
        while ($answered->next()) {
            $this->sendMessage($answered->chat_id, $answered->answer, ParseMode::TEXT);
            DB::exec("DELETE FROM [messagequeue] WHERE id=$answered->id");
        }
    }

    
    protected function getTypingIds() : DBView {
        
        $sql = <<<FIN
                SELECT DISTINCT sender_id AS user_id FROM [messagequeue]
                WHERE sender_id = chat_id AND is_typing = 1
                FIN;
        $view = new DBView($sql);
        
        return $view;
    }
    
    protected function getAnsweredMessages() : DBView {
        
        $sql = <<<FIN
                SELECT id, chat_id, answer FROM [messagequeue]
                WHERE answer IS NOT NULL
                ORDER BY received
                FIN;
        $view = new DBView($sql);
        
        return $view;
    }
    
    //#[Cron(period: 60.0)]
    public function startDispatcher(): void
    {
        $view = $this->getUserIdsView();
        
        while ($view->next()) {
            if (random_int(1, 1) <> 1) {
                continue;
            }
            
            if ($this->getLock("$view->user_id")) {
                //$this->releaseLock("$view->user_id");
                $this->callFork(fn($id) => $this->userHandler($id), $view->user_id);
            }
        }
    }
    
    
    protected function userLoop(int $user_id) {
    
        error_log("userLoop started for user_id=$user_id");
        $time = time();
        while (time()-$time <= static::OPT_WAIT_USER) {
            if ($this->processUserMessages($user_id)) {
                $time = time();
            }
            $this->sleep(10);
        }
    }
    
    
    
    #[FilterRegex("/\S/")]
    public function textHandler(Incoming&Message $message) : void {
        MessageQueue::add($message->senderId, $message->chatId, $message->id, $message->message);
    }
    
    #[FilterVoice()]
    public function voiceHandler(Incoming&Message $message) : void {
        $this->callFork(fn($msg) => $this->queueVoice($msg), $message);
    }
    
    
    public function queueVoice(Incoming&Message $message) {
        DB::reconnect();
        $filename = tempnam(sys_get_temp_dir(), 'voice_');
        $message->media->downloadToFile($filename);
        MessageQueue::add($message->senderId, $message->chatId, $message->id, $filename, MessageQueue::TYPE_VOICE);
    }
    
    public function anyHandler(Incoming&Message $message) : void {
        
        error_log('got message');
        $user_id = (string)$message->chatId;

        $this->message_queue[$user_id][] = $message;

        if (!empty($this->ai_busy[$user_id])) {
            return;
        }
        
        $this->ai_busy[$user_id] = true;
        $this->processMessageQueue($user_id);
        
    }
    
    /**
     * Gets the text representation of the message
     * @param Message $message
     * @return string|null
     */
    protected function getText(Message $message) : ?string {
        if ($message->message) {
            return $message->message;
        } elseif ($message->media && is_a($message->media, Voice::class)) {
            
            $filename = tempnam(sys_get_temp_dir(), 'voice_');
            
            $message->media->downloadToFile($filename);
            
            $folder_id = ReflexA::getConfig('yandex-ai', 'folder_id');
            $stt = new YandexSpeachKitGateway($folder_id);
            
            $bytes = read($filename);
            $text = $stt->recognize($bytes);
            
            return "Голосовое сообщение:\n$text";
        } else {
            return null;
        }
    }
    
    /**
     * Processes the current message queue
     * @param string $user_id
     */
    protected function processMessageQueue(string $user_id) {

        $user_settings = UserSettings::getById($user_id);
        $char_step = $user_settings->chars_per_minute / 30;
        
        while ($message = array_shift($this->message_queue[$user_id])) {

            $text = $this->getText($message);
            if (!$text) {
                continue;
            }
            
            $user_id = $message->chatId;

            $brain = new ReflexA($user_id);
            $answer = $brain->query($text);

            $len = strlen($answer);
            for ($i=0;$i<$len;$i+=$char_step) {
                $this->messages->setTyping(
                        action: ['_' => 'sendMessageTypingAction'], 
                        peer: $user_id);
                $this->sleep(2);
            }

            if (count($this->message_queue[$user_id])) {
                $message->reply($answer);
                error_log('reply sent');
            } else {
                $this->sendMessage($user_id, $answer, \danog\MadelineProto\ParseMode::TEXT);
                error_log('answer sent');
            }

        }
        
        $this->ai_busy[$user_id] = false;
    }
}
