<?php

use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

test('telegram service does not attempt to send when token is missing', function () {
    config()->set('services.telegram.bot_token', '');

    Log::spy();

    $service = new TelegramService;
    $ok = $service->sendMessage(123, 'hello');

    expect($ok)->toBeFalse();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'Telegram bot token missing'))
        ->once();
});
