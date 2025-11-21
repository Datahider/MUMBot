<?php

use losthost\DB\DB;
use losthost\MUMBot\Data\MessageQueue;
use losthost\MUMBot\Dispatcher;

require_once 'vendor/autoload.php';
require_once 'etc/config.php';

DB::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PREF);
MessageQueue::initDataStructure();

$dispatcher = new Dispatcher();

while (true) {
    $dispatcher->dispatch();
    sleep(20);
}