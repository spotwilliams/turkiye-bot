<?php

use App\Mail\TaskReminderMail;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

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

test('reminders:process emails due reminders for web-origin tasks', function () {
    Carbon::setTestNow('2026-04-23 09:00:00');
    Mail::fake();

    $user = User::factory()->create(['email' => 'parent@example.com']);
    $task = Task::factory()->create([
        'telegram_chat_id' => null,
        'created_by' => $user->id,
        'status' => 'pending',
    ]);
    $reminder = Reminder::factory()->create([
        'task_id' => $task->id,
        'scheduled_at' => now()->subMinute(),
        'sent' => false,
        'message' => 'Pack item',
    ]);

    $this->artisan('reminders:process')->assertSuccessful();

    Mail::assertQueued(TaskReminderMail::class, fn (TaskReminderMail $mail) => $mail->hasTo('parent@example.com'));
    expect($reminder->refresh()->sent)->toBeTrue();
});

test('reminders:process skips cancelled tasks on every channel but still marks reminders sent', function () {
    Carbon::setTestNow('2026-04-23 09:00:00');
    Mail::fake();

    $user = User::factory()->create();
    $task = Task::factory()->create([
        'telegram_chat_id' => null,
        'created_by' => $user->id,
        'status' => 'cancelled',
    ]);
    $reminder = Reminder::factory()->create([
        'task_id' => $task->id,
        'scheduled_at' => now()->subMinute(),
        'sent' => false,
    ]);

    $this->artisan('reminders:process')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($reminder->refresh()->sent)->toBeTrue();
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
