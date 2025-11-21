<?php

namespace losthost\MUMBot\Data;

use losthost\DB\DBObject;

class MessageQueue extends DBObject {
    
    const TYPE_TEXT = 'text';
    const TYPE_JSON = 'json';
    const TYPE_VOICE = 'voice';
    
    const METADATA = [
        'id' => 'BIGINT NOT NULL AUTO_INCREMENT',
        'received' => 'DATETIME NOT NULL',
        'sender_id' => 'BIGINT',
        'chat_id' => 'BIGINT',
        'message_id' => 'BIGINT NOT NULL',
        'type' => 'ENUM("text", "json", "voice")', // See consts TYPE_...
        'content' => 'TEXT',
        'is_typing' => 'TINYINT(1) NOT NULL',
        'answer' => 'TEXT',
        'PRIMARY KEY' => 'id'
    ];

    static public function build(array $data = [], bool $create = false) {
        $me = new static($data, $create);
        return $me;
    }
    
    static public function add(int $sender_id, int $chat_id, int $message_id, string $content, string $type='text') {
        $me = static::build();
        $me->sender_id = $sender_id;
        $me->chat_id = $chat_id;
        $me->message_id = $message_id;
        $me->type = $type;
        $me->content = $content;
        $me->is_typing = false;
        $me->received = date_create();
        $me->write();
        return $me;
    }
}
