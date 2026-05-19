<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.telegram.bot_token', 'TOKEN');
    config()->set('services.telegram.webhook_secret', 'SECRET');
});

test('telegram:set-webhook --url registers the webhook with the configured secret', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $this->artisan('telegram:set-webhook', ['--url' => 'https://example.test/api/telegram/webhook'])
        ->assertSuccessful();

    Http::assertSent(function ($req) {
        return str_ends_with($req->url(), '/setWebhook')
            && $req['url'] === 'https://example.test/api/telegram/webhook'
            && $req['secret_token'] === 'SECRET';
    });
});

test('telegram:set-webhook fails when no flags are provided', function () {
    Http::fake();

    $this->artisan('telegram:set-webhook')->assertFailed();

    Http::assertNothingSent();
});

test('telegram:set-webhook fails when both --url and --ngrok are provided', function () {
    Http::fake();

    $this->artisan('telegram:set-webhook', ['--url' => 'https://x.test', '--ngrok' => true])
        ->assertFailed();

    Http::assertNothingSent();
});

test('telegram:set-webhook --ngrok picks the first HTTPS tunnel', function () {
    Http::fake([
        'localhost:4040/api/tunnels' => Http::response([
            'tunnels' => [
                ['public_url' => 'http://aaa.ngrok.io'],
                ['public_url' => 'https://bbb.ngrok.io'],
            ],
        ]),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $this->artisan('telegram:set-webhook', ['--ngrok' => true])->assertSuccessful();

    Http::assertSent(function ($req) {
        return str_ends_with($req->url(), '/setWebhook')
            && $req['url'] === 'https://bbb.ngrok.io/api/telegram/webhook';
    });
});

test('telegram:set-webhook --ngrok fails when no HTTPS tunnel is found', function () {
    Http::fake([
        'localhost:4040/api/tunnels' => Http::response(['tunnels' => [
            ['public_url' => 'http://only-http.test'],
        ]]),
    ]);

    $this->artisan('telegram:set-webhook', ['--ngrok' => true])->assertFailed();

    Http::assertNotSent(fn ($req) => str_contains($req->url(), 'api.telegram.org'));
});

test('telegram:set-webhook surfaces a failed Telegram response as command failure', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'bad token'], 401),
    ]);

    $this->artisan('telegram:set-webhook', ['--url' => 'https://x.test'])
        ->assertFailed();
});
