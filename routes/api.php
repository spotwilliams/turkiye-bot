<?php

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\VerifyTelegramWebhook;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->middleware(VerifyTelegramWebhook::class)
    ->name('telegram.webhook');
