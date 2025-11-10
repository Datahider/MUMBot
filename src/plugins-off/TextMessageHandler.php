<?php  declare(strict_types=1);

namespace MadelinePlugin;

use danog\MadelineProto\PluginEventHandler;
use danog\MadelineProto\EventHandler\Filter\FilterRegex;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Filter\FilterText;
use danog\MadelineProto\EventHandler\Message;

class TextMessageHandler extends PluginEventHandler {

    public function isPluginEnabled(): bool
    {
        return true;
    }

    public function __sleep(): array
    {
        return [];
    }

//    public function onStart(): void
//    {
//        $this->logger("Text message handler started!");
//        $this->sendMessageToAdmins("Теперь я могу отвечать на текстовые сообщения");
//    }

    #[Handler]
    public function textHandler(Incoming & Message $message): void
    {
        $message->reply('🤔');
    }

    #[FilterText('111')]
    public function pingCommand(Incoming&Message $message): void
    {
        $message->reply('222');
        error_log('got 111');
    }
    
}
