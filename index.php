<?php 

use losthost\MUMBot\MainEventHandler;
use losthost\MUMBot\Data\UserSettings;
use losthost\ReflexA\ReflexA;

require_once 'vendor/autoload.php';

ReflexA::initDB();

UserSettings::initDataStructure();

MainEventHandler::startAndLoop('mumbot.madeline');