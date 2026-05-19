<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramWebhookInfo extends Command
{
    protected $signature = 'telegram:webhook-info';

    protected $description = 'Show Telegram\'s view of the current webhook (url, pending count, last error).';

    public function handle(TelegramService $telegram): int
    {
        $result = $telegram->getWebhookInfo();

        if (! $result['ok']) {
            $this->error('Telegram getWebhookInfo failed: '.($result['body']['description'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $info = (array) ($result['body']['result'] ?? []);

        $this->line('URL:                  '.($info['url'] ?? '(unset)'));
        $this->line('Pending updates:      '.($info['pending_update_count'] ?? 0));
        $this->line('Last error message:   '.($info['last_error_message'] ?? '(none)'));
        $this->line('Last error timestamp: '.($info['last_error_date'] ?? '(none)'));

        return self::SUCCESS;
    }
}
