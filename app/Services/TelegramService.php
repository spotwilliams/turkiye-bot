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

    public function sendProcessedConfirmation(int $chatId, Message $message, int $taskCount): bool
    {
        $text = "Message processed.\n\n";
        $text .= "Summary: {$message->summary}\n";
        $text .= "Tasks created: {$taskCount}";

        return $this->sendMessage($chatId, $text);
    }
}
