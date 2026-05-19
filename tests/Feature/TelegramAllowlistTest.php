<?php

use App\Jobs\ProcessSchoolMessage;
use App\Models\FamilyMember;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

function postWebhook(string $text, int $fromId, int $chatId)
{
    return test()->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
        ->postJson(route('telegram.webhook'), [
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => $chatId],
                'from' => ['id' => $fromId],
                'text' => $text,
            ],
        ]);
}

test('an unknown sender posting plain text is silently ignored', function () {
    Queue::fake();

    $telegram = $this->mock(TelegramService::class, function (MockInterface $m) {
        $m->shouldNotReceive('sendMessage');
    });
    $this->app->instance(TelegramService::class, $telegram);

    postWebhook('hello school message', fromId: 9999, chatId: 9999)
        ->assertSuccessful()
        ->assertJson(['ok' => true, 'rejected' => 'unknown_sender']);

    Queue::assertNothingPushed();
});

test('an unknown sender sending a slash command gets an onboarding hint', function () {
    Queue::fake();

    $reply = null;
    $telegram = $this->mock(TelegramService::class, function (MockInterface $m) use (&$reply) {
        $m->shouldReceive('sendMessage')->once()
            ->andReturnUsing(function (int $chatId, string $text) use (&$reply) {
                $reply = $text;

                return true;
            });
    });
    $this->app->instance(TelegramService::class, $telegram);

    postWebhook('/pending', fromId: 9999, chatId: 9999)
        ->assertSuccessful()
        ->assertJson(['rejected' => 'unknown_sender']);

    expect($reply)->toContain('Not registered');
    Queue::assertNothingPushed();
});

test('an allowlisted sender sending plain text dispatches the ingest job', function () {
    Queue::fake();

    FamilyMember::factory()->create(['telegram_user_id' => 4242]);

    postWebhook('Yarin okul gezisi.', fromId: 4242, chatId: 4242)
        ->assertSuccessful();

    Queue::assertPushed(ProcessSchoolMessage::class);
});

test('hitting the rate limit drops the message with a slow-down reply', function () {
    Queue::fake();

    FamilyMember::factory()->create(['telegram_user_id' => 7777]);

    // Burn through the burst allowance (5/min) silently.
    $replies = [];
    $telegram = $this->mock(TelegramService::class, function (MockInterface $m) use (&$replies) {
        $m->shouldReceive('sendMessage')->andReturnUsing(function (int $chatId, string $text) use (&$replies) {
            $replies[] = $text;

            return true;
        });
    });
    $this->app->instance(TelegramService::class, $telegram);

    for ($i = 0; $i < 5; $i++) {
        postWebhook("ok message {$i}", fromId: 7777, chatId: 7777)->assertSuccessful();
    }

    // 6th call within the same minute should be rejected.
    postWebhook('one more', fromId: 7777, chatId: 7777)
        ->assertSuccessful()
        ->assertJson(['rejected' => 'rate_limited']);

    Queue::assertPushed(ProcessSchoolMessage::class, 5);
    expect(end($replies))->toContain('Slow down');
});

test('cheap commands do not consume the ingest rate-limit budget', function () {
    Queue::fake();

    FamilyMember::factory()->create(['telegram_user_id' => 3333]);

    $telegram = $this->mock(TelegramService::class, function (MockInterface $m) {
        $m->shouldReceive('sendMessage')->andReturnTrue();
    });
    $this->app->instance(TelegramService::class, $telegram);

    // Spam 20 /pending commands — none should count against ingest budget.
    for ($i = 0; $i < 20; $i++) {
        postWebhook('/pending', fromId: 3333, chatId: 3333)->assertSuccessful();
    }

    // The next plain-text ingest must still be accepted.
    postWebhook('first real school message', fromId: 3333, chatId: 3333)
        ->assertSuccessful()
        ->assertJsonMissing(['rejected' => 'rate_limited']);

    Queue::assertPushed(ProcessSchoolMessage::class, 1);
});

test('rate limit is isolated per from id', function () {
    Queue::fake();

    FamilyMember::factory()->create(['telegram_user_id' => 1111]);
    FamilyMember::factory()->create(['telegram_user_id' => 2222]);

    for ($i = 0; $i < 5; $i++) {
        postWebhook("a {$i}", fromId: 1111, chatId: 1111)->assertSuccessful();
    }

    postWebhook('b', fromId: 2222, chatId: 2222)
        ->assertSuccessful()
        ->assertJsonMissing(['rejected' => 'rate_limited']);
});
