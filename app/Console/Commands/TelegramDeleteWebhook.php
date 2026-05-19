<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramDeleteWebhook extends Command
{
    protected $signature = 'telegram:delete-webhook';

    protected $description = 'Remove the Telegram webhook for this bot (returns to long-polling mode).';

    public function handle(TelegramService $telegram): int
    {
        $result = $telegram->deleteWebhook();

        if (! $result['ok']) {
            $this->error('Telegram deleteWebhook failed: '.($result['body']['description'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->info('Webhook deleted.');

        return self::SUCCESS;
    }
}
