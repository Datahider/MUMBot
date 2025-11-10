<?php


namespace losthost\MUMBot;

use losthost\ReflexA\ReflexA;

class AIModel {
    
    static ReflexA $reflexa;
    
    static public function init() {
        
        self::$reflexa = new ReflexA();
    }
}
