<?php

use App\Models\FamilyMember;
use App\Models\Task;
use App\Services\TelegramService;
use Illuminate\Support\Carbon;

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
