<?php
namespace App\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Objects\Update;

class UnbundKeyCommand extends Command
{
    protected string $name = 'unbind_key';
    protected string $description = 'Отвязать ключ';

    public function handle()
    {
        $this->replyWithMessage([
            'text' => 'Привет! Я ваш бот, готов помочь!'
        ]);
    }
}
