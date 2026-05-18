<?php

use App\Jobs\ProcessSchoolMessage;
use App\Models\Message;
use App\Services\TelegramService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Queue;

test('duplicate message in same chat is not dispatched and gets ack', function () {
    Queue::fake();

    $existing = Message::factory()->create([
        'telegram_chat_id' => 555,
        'original_text' => 'Yarin 350 TL getiriniz.',
        'normalized_text' => Message::normalizeText('Yarin 350 TL getiriniz.'),
        'normalized_text_hash' => Message::hashText('Yarin 350 TL getiriniz.'),
    ]);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendDuplicateAck')
        ->once()
        ->withArgs(fn (int $chatId, Message $m) => $chatId === 555 && $m->id === $existing->id)
        ->andReturnTrue();
    $this->app->instance(TelegramService::class, $telegram);

    $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson(route('telegram.webhook'), [
        'message' => [
            'message_id' => 99,
            'chat' => ['id' => 555], 'from' => ['id' => 555],
            'text' => 'Yarin 350 TL getiriniz.',
        ],
    ]);

    $response->assertSuccessful()->assertJson(['ok' => true, 'duplicate' => true]);
    Queue::assertNothingPushed();
});

test('normalized variants (whitespace/case) are treated as duplicates', function () {
    Queue::fake();

    Message::factory()->create([
        'telegram_chat_id' => 1,
        'original_text' => 'Yarin 350 TL getiriniz.',
        'normalized_text' => Message::normalizeText('Yarin 350 TL getiriniz.'),
        'normalized_text_hash' => Message::hashText('Yarin 350 TL getiriniz.'),
    ]);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendDuplicateAck')->once()->andReturnTrue();
    $this->app->instance(TelegramService::class, $telegram);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson(route('telegram.webhook'), [
        'message' => [
            'message_id' => 100,
            'chat' => ['id' => 1], 'from' => ['id' => 1],
            'text' => "  YARIN   350 tl    getiriniz.\n",
        ],
    ])->assertSuccessful()->assertJson(['duplicate' => true]);

    Queue::assertNothingPushed();
});

test('same text from another chat is also a duplicate (global text scope)', function () {
    Queue::fake();

    Message::factory()->create([
        'telegram_chat_id' => 1,
        'original_text' => 'Yarin 350 TL getiriniz.',
        'normalized_text' => Message::normalizeText('Yarin 350 TL getiriniz.'),
        'normalized_text_hash' => Message::hashText('Yarin 350 TL getiriniz.'),
    ]);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendDuplicateAck')->once()->andReturnTrue();
    $this->app->instance(TelegramService::class, $telegram);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson(route('telegram.webhook'), [
        'message' => [
            'message_id' => 100,
            'chat' => ['id' => 2], 'from' => ['id' => 2],
            'text' => 'Yarin 350 TL getiriniz.',
        ],
    ])->assertSuccessful()->assertJson(['duplicate' => true]);

    Queue::assertNothingPushed();
});

test('first unique message follows normal dispatch flow', function () {
    Queue::fake();

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson(route('telegram.webhook'), [
        'message' => [
            'message_id' => 7,
            'chat' => ['id' => 42], 'from' => ['id' => 42],
            'text' => 'Hello world',
        ],
    ])->assertSuccessful()->assertJsonMissing(['duplicate' => true]);

    Queue::assertPushed(ProcessSchoolMessage::class, function (ProcessSchoolMessage $job): bool {
        return $job->chatId === 42 && $job->messageText === 'Hello world';
    });
});

test('database unique constraint prevents duplicate inserts under race', function () {
    Message::factory()->create([
        'telegram_chat_id' => 10,
        'original_text' => 'Race text',
        'normalized_text' => Message::normalizeText('Race text'),
        'normalized_text_hash' => Message::hashText('Race text'),
    ]);

    expect(fn () => Message::factory()->create([
        'telegram_chat_id' => 10,
        'original_text' => 'Race text',
        'normalized_text' => Message::normalizeText('Race text'),
        'normalized_text_hash' => Message::hashText('Race text'),
    ]))->toThrow(UniqueConstraintViolationException::class);
});
