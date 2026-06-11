<?php

use App\Mail\TaskReminderMail;
use App\Models\Task;
use App\Models\User;
use App\Services\Reminders\ReminderNotifier;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Mail;

test('notifier routes a telegram-origin task to Telegram, not mail', function () {
    Mail::fake();

    $task = Task::factory()->create([
        'telegram_chat_id' => 555,
        'created_by' => null,
    ]);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendMessage')
        ->once()
        ->with(555, 'Reminder: Pack bag')
        ->andReturnTrue();
    $this->app->instance(TelegramService::class, $telegram);

    app(ReminderNotifier::class)->sendReminder($task, 'Pack bag');

    Mail::assertNothingSent();
});

test('notifier routes a web-origin task to email the creator, not Telegram', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'parent@example.com']);
    $task = Task::factory()->create([
        'telegram_chat_id' => null,
        'created_by' => $user->id,
    ]);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldNotReceive('sendMessage');
    $this->app->instance(TelegramService::class, $telegram);

    app(ReminderNotifier::class)->sendReminder($task, 'Pack bag');

    Mail::assertQueued(TaskReminderMail::class, fn (TaskReminderMail $mail) => $mail->hasTo('parent@example.com'));
});
