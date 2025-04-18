<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\TelegramBotController;

class SetBotCommands extends Command {
    protected $signature = 'telegram:set-commands';
    protected $description = 'Register Telegram bot commands for command suggestions';

    public function handle() {
        $controller = new TelegramBotController();
        $response = $controller->setBotCommands();

        if ($response->getStatusCode() === 200) {
            $this->info('Telegram bot commands registered successfully.');
        } 
        else {
            $this->error('Failed to register Telegram bot commands.');
        }
    }
}