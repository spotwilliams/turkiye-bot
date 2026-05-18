<?php

use App\Jobs\ProcessSchoolMessage;
use Illuminate\Support\Facades\Queue;

test('it dispatches message processing when telegram message payload is valid', function () {
    Queue::fake();

    $payload = [
        'message' => [
            'message_id' => 73,
            'chat' => ['id' => 998877], 'from' => ['id' => 998877],
            'text' => 'Okula yarin 2 A4 kagidi getiriniz.',
        ],
    ];

    $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson(route('telegram.webhook'), $payload);

    $response->assertSuccessful()->assertJson(['ok' => true]);

    Queue::assertPushed(ProcessSchoolMessage::class, function (ProcessSchoolMessage $job): bool {
        return $job->chatId === 998877
            && $job->messageId === 73
            && $job->messageText === 'Okula yarin 2 A4 kagidi getiriniz.';
    });
});

test('it ignores payloads without a message body', function () {
    Queue::fake();

    $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson(route('telegram.webhook'), ['update_id' => 1]);

    $response->assertSuccessful()->assertJson([
        'ok' => true,
        'ignored' => true,
    ]);

    Queue::assertNothingPushed();
});
