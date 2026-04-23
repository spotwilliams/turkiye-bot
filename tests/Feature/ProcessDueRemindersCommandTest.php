<?php

use App\Models\Reminder;
use App\Models\Task;
use App\Services\TelegramService;
use Illuminate\Support\Carbon;

test('reminders:process sends due reminders and marks them sent', function () {
    Carbon::setTestNow('2026-04-23 09:00:00');

    $task = Task::factory()->create([
        'telegram_chat_id' => 555,
        'status' => 'pending',
    ]);

    $dueReminder = Reminder::factory()->create([
        'task_id' => $task->id,
        'scheduled_at' => now()->subMinute(),
        'sent' => false,
        'message' => 'Pack item',
    ]);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendMessage')
        ->once()
        ->with(555, 'Reminder: Pack item')
        ->andReturnTrue();

    $this->app->instance(TelegramService::class, $telegram);

    $this->artisan('reminders:process')->assertSuccessful();

    $dueReminder->refresh();
    expect($dueReminder->sent)->toBeTrue();
    expect($dueReminder->sent_at)->not->toBeNull();
});

test('reminders:process skips completed tasks but still marks reminders sent', function () {
    Carbon::setTestNow('2026-04-23 09:00:00');

    $task = Task::factory()->create([
        'telegram_chat_id' => 777,
        'status' => 'completed',
    ]);

    $dueReminder = Reminder::factory()->create([
        'task_id' => $task->id,
        'scheduled_at' => now()->subMinute(),
        'sent' => false,
    ]);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldNotReceive('sendMessage');

    $this->app->instance(TelegramService::class, $telegram);

    $this->artisan('reminders:process')->assertSuccessful();

    $dueReminder->refresh();
    expect($dueReminder->sent)->toBeTrue();
});
