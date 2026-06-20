<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.telegram.bot_token', 'TOKEN');
});

test('telegram:webhook-info prints url and pending count from the Telegram response', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => [
                'url' => 'https://x.test/api/telegram/webhook',
                'pending_update_count' => 3,
                'last_error_message' => 'connection timed out',
            ],
        ]),
    ]);

    $this->artisan('telegram:webhook-info')
        ->assertSuccessful()
        ->expectsOutputToContain('https://x.test/api/telegram/webhook')
        ->expectsOutputToContain('3')
        ->expectsOutputToContain('connection timed out');

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/getWebhookInfo'));
});

test('telegram:webhook-info surfaces a failed getWebhookInfo response as command failure', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'bad token'], 401),
    ]);

    $this->artisan('telegram:webhook-info')
        ->assertFailed()
        ->expectsOutputToContain('bad token');
});

test('telegram:delete-webhook calls deleteWebhook', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $this->artisan('telegram:delete-webhook')->assertSuccessful();

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/deleteWebhook'));
});

test('telegram:delete-webhook surfaces a failed deleteWebhook response as command failure', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'bad token'], 401),
    ]);

    $this->artisan('telegram:delete-webhook')
        ->assertFailed()
        ->expectsOutputToContain('bad token');
});
