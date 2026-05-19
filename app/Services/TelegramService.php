<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function sendMessage(int $chatId, string $text, array $options = []): bool
    {
        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            Log::warning('Telegram bot token missing. Skipping sendMessage call.');

            return false;
        }

        $response = Http::baseUrl("https://api.telegram.org/bot{$token}")
            ->timeout(10)
            ->connectTimeout(5)
            ->asJson()
            ->post('/sendMessage', array_merge([
                'chat_id' => $chatId,
                'text' => $text,
            ], $options));

        if ($response->failed()) {
            Log::error('Telegram send failed', [
                'chat_id' => $chatId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    public function sendDuplicateAck(int $chatId, Message $existing): bool
    {
        $text = "ℹ️ I already received this message and it's being tracked (message #{$existing->id}).";

        return $this->sendMessage($chatId, $text);
    }

    /**
     * @return array{ok: bool, body: array<string, mixed>}
     */
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        return $this->call('/setWebhook', array_filter([
            'url' => $url,
            'secret_token' => $secretToken,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * @return array{ok: bool, body: array<string, mixed>}
     */
    public function getWebhookInfo(): array
    {
        return $this->call('/getWebhookInfo', []);
    }

    /**
     * @return array{ok: bool, body: array<string, mixed>}
     */
    public function deleteWebhook(): array
    {
        return $this->call('/deleteWebhook', []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>}
     */
    private function call(string $endpoint, array $payload): array
    {
        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            Log::warning('Telegram bot token missing. Skipping API call.', ['endpoint' => $endpoint]);

            return ['ok' => false, 'body' => ['description' => 'TELEGRAM_BOT_TOKEN is empty.']];
        }

        $response = Http::baseUrl("https://api.telegram.org/bot{$token}")
            ->timeout(10)
            ->connectTimeout(5)
            ->asJson()
            ->post($endpoint, $payload);

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        return ['ok' => $response->successful() && ($body['ok'] ?? false) === true, 'body' => $body];
    }

    public function sendProcessedConfirmation(int $chatId, Message $message, int $taskCount): bool
    {
        $text = "Message processed.\n\n";
        $text .= "Summary: {$message->summary}\n";
        $text .= "Tasks created: {$taskCount}";

        return $this->sendMessage($chatId, $text);
    }
}
