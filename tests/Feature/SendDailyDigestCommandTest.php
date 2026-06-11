<?php

use App\Mail\DailyDigestMail;
use App\Models\FamilyMember;
use App\Models\Task;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

test('digest:send sends a summary message per distinct chat id', function () {
    Carbon::setTestNow('2026-04-23 09:00:00');

    $chatId = 12345;

    FamilyMember::factory()->create(['telegram_chat_id' => $chatId]);
    FamilyMember::factory()->create(['telegram_chat_id' => $chatId]);

    Task::factory()->create([
        'telegram_chat_id' => $chatId,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
    ]);
    Task::factory()->create([
        'telegram_chat_id' => $chatId,
        'due_date' => now()->addDay()->toDateString(),
        'status' => 'pending',
    ]);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendMessage')
        ->once()
        ->withArgs(function (int $sentChatId, string $text): bool {
            return $sentChatId === 12345
                && str_contains($text, 'Today: 1')
                && str_contains($text, 'Tomorrow: 1');
        })
        ->andReturnTrue();

    $this->app->instance(TelegramService::class, $telegram);

    $this->artisan('digest:send')->assertSuccessful();
});

test('digest:send sends an all-set message to a chat with no pending tasks', function () {
    Carbon::setTestNow('2026-04-23 09:00:00');

    FamilyMember::factory()->create(['telegram_chat_id' => 999]);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendMessage')
        ->once()
        ->withArgs(fn (int $chatId, string $text) => $chatId === 999 && str_contains(strtolower($text), 'all set'))
        ->andReturnTrue();
    $this->app->instance(TelegramService::class, $telegram);

    $this->artisan('digest:send')->assertSuccessful();
});

test('digest:send emails a digest to each web-origin task creator', function () {
    Carbon::setTestNow('2026-04-23 09:00:00');
    Mail::fake();

    // No family members → no Telegram recipients, but a web creator still gets email.
    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldNotReceive('sendMessage');
    $this->app->instance(TelegramService::class, $telegram);

    $user = User::factory()->create(['email' => 'parent@example.com']);
    Task::factory()->create([
        'telegram_chat_id' => null,
        'created_by' => $user->id,
        'status' => 'pending',
        'due_date' => now()->toDateString(),
    ]);

    $this->artisan('digest:send')->assertSuccessful();

    Mail::assertQueued(DailyDigestMail::class, fn (DailyDigestMail $mail) => $mail->hasTo('parent@example.com'));
});
