<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook
        {--url= : Public HTTPS URL pointing at /api/telegram/webhook}
        {--ngrok : Auto-detect public URL from local ngrok agent (http://localhost:4040)}';

    protected $description = 'Register the Telegram webhook for this bot, including the configured secret token.';

    public function handle(TelegramService $telegram): int
    {
        $url = (string) $this->option('url');
        $ngrok = (bool) $this->option('ngrok');

        if (($url === '' && ! $ngrok) || ($url !== '' && $ngrok)) {
            $this->error('Pass exactly one of --url=<url> or --ngrok.');

            return self::FAILURE;
        }

        if ($ngrok) {
            $resolved = $this->resolveNgrokUrl();
            if ($resolved === null) {
                return self::FAILURE;
            }
            $url = $resolved;
        }

        $secret = (string) config('services.telegram.webhook_secret');
        $result = $telegram->setWebhook($url, $secret !== '' ? $secret : null);

        if (! $result['ok']) {
            $this->error('Telegram setWebhook failed: '.($result['body']['description'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->info("Webhook registered: {$url}");

        return self::SUCCESS;
    }

    private function resolveNgrokUrl(): ?string
    {
        try {
            $response = Http::timeout(5)->get('http://localhost:4040/api/tunnels');
        } catch (\Throwable $e) {
            $this->error('Could not reach ngrok at http://localhost:4040 — is `ngrok http 80` running?');

            return null;
        }

        if (! $response->successful()) {
            $this->error('ngrok local API returned an error.');

            return null;
        }

        $tunnels = $response->json('tunnels') ?? [];
        foreach ($tunnels as $tunnel) {
            $public = (string) ($tunnel['public_url'] ?? '');
            if (str_starts_with($public, 'https://')) {
                return rtrim($public, '/').'/api/telegram/webhook';
            }
        }

        $this->error('No HTTPS tunnel found in ngrok response.');

        return null;
    }
}
