<?php declare(strict_types=1);

namespace losthost\MUMBot;

use danog\MadelineProto\EventHandler;
class MainEventHandler extends EventHandler {

    public const ADMIN = "@mybiztrainer"; // !!! Change this to your username !!!
    
    public function getReportPeers()
    {
        return [self::ADMIN];
    }

    public static function getPluginPaths(): string|array|null
    {
        return 'plugins/';
    }
    
    public function onStart(): void {
        $this->logger("The bot was started!");
        $this->logger($this->getFullInfo('MadelineProto'));

        $this->sendMessageToAdmins("Привет! Я онлайн, если что 😉");
    }
}
