<?php

use App\Actions\RedeemFamilyInvite;
use App\Models\FamilyInvite;
use App\Models\FamilyMember;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

function postStart(string $text, int $fromId, int $chatId)
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

test('/start with a valid code registers the family member and replies with a welcome', function () {
    Queue::fake();

    FamilyInvite::create([
        'code' => 'welcome-code',
        'name' => 'Ricardo',
        'role' => 'father',
    ]);

    $reply = null;
    $telegram = $this->mock(TelegramService::class, function (MockInterface $m) use (&$reply) {
        $m->shouldReceive('sendMessage')->once()
            ->andReturnUsing(function (int $chatId, string $text) use (&$reply) {
                $reply = $text;

                return true;
            });
    });
    $this->app->instance(TelegramService::class, $telegram);

    postStart('/start welcome-code', fromId: 555, chatId: 777)
        ->assertSuccessful()
        ->assertJson(['ok' => true, 'command' => 'start']);

    expect(FamilyMember::where('telegram_user_id', 555)->exists())->toBeTrue();
    expect($reply)->toContain('Welcome');
    Queue::assertNothingPushed();
});

test('/start with an unknown code returns a generic invalid reply and creates no member', function () {
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

    postStart('/start nope', fromId: 1, chatId: 1)
        ->assertSuccessful()
        ->assertJson(['result' => 'invalid']);

    expect(FamilyMember::count())->toBe(0);
    expect($reply)->toContain('invalid');
});

test('/start with no code returns usage help', function () {
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

    postStart('/start', fromId: 1, chatId: 1)
        ->assertSuccessful()
        ->assertJson(['result' => 'usage']);

    expect($reply)->toContain('Usage');
});

test('/start by an already-registered user is idempotent', function () {
    Queue::fake();

    FamilyInvite::create(['code' => 'first', 'name' => 'Ricardo', 'role' => 'father']);
    app(RedeemFamilyInvite::class)->execute('first', 42, 42);

    FamilyInvite::create(['code' => 'second', 'name' => 'Other', 'role' => 'mother']);

    $reply = null;
    $telegram = $this->mock(TelegramService::class, function (MockInterface $m) use (&$reply) {
        $m->shouldReceive('sendMessage')->once()
            ->andReturnUsing(function (int $chatId, string $text) use (&$reply) {
                $reply = $text;

                return true;
            });
    });
    $this->app->instance(TelegramService::class, $telegram);

    postStart('/start second', fromId: 42, chatId: 42)
        ->assertSuccessful()
        ->assertJson(['result' => 'already_registered']);

    expect(FamilyMember::count())->toBe(1);
    expect($reply)->toContain('already registered');
});
