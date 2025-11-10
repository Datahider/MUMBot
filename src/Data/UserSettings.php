<?php

namespace losthost\MUMBot\Data;

use losthost\DB\DBObject;

class UserSettings extends DBObject {
    
    const METADATA = [
        'id' => 'VARCHAR(50) NOT NULL',
        'chars_per_minute' => 'INT NOT NULL DEFAULT 300',
        'PRIMARY KEY' => 'id'
    ];
    
    static public function getById($id) {
        
        $object = new static(['id' => $id], true);
        if ($object->isNew()) {
            $object->chars_per_minute = 300;
            $object->write();
        }
        
        return $object;
    }
}
