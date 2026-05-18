<?php

use Illuminate\Support\Facades\Queue;

$validPayload = [
    'message' => [
        'message_id' => 1,
        'chat' => ['id' => 1],
        'from' => ['id' => 1],
        'text' => 'hello',
    ],
];

test('it rejects requests without the secret token header', function () use ($validPayload) {
    Queue::fake();

    $response = $this->postJson(route('telegram.webhook'), $validPayload);

    $response->assertForbidden();
    Queue::assertNothingPushed();
});

test('it ignores payloads missing the from.id field', function () {
    Queue::fake();

    $payload = [
        'message' => [
            'message_id' => 1,
            'chat' => ['id' => 1],
            'text' => 'hello',
        ],
    ];

    $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
        ->postJson(route('telegram.webhook'), $payload);

    $response->assertSuccessful()->assertJson(['ok' => true, 'ignored' => true]);
    Queue::assertNothingPushed();
});

test('it rejects requests whose secret token header does not match', function () use ($validPayload) {
    Queue::fake();

    $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong-secret')
        ->postJson(route('telegram.webhook'), $validPayload);

    $response->assertForbidden();
    Queue::assertNothingPushed();
});
