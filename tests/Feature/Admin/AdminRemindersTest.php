<?php

use App\Models\Message;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;

test('unauthenticated users are redirected from reminders index', function () {
    $this->get(route('admin.reminders.index'))->assertRedirect();
});

test('reminders index renders paginated reminders', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $message = Message::factory()->create();
    $task = Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
    ]);
    Reminder::factory()->count(3)->create(['task_id' => $task->id]);

    $this->actingAs($user)
        ->get(route('admin.reminders.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Reminders/Index')
            ->has('reminders.data', 3)
            ->has('reminders.data.0.task')
            ->has('reminders.data.0.ref')
        );
});

test('reminders index filters by sent', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $message = Message::factory()->create();
    $task = Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
    ]);
    Reminder::factory()->create(['task_id' => $task->id, 'sent' => true, 'sent_at' => now()]);
    Reminder::factory()->create(['task_id' => $task->id, 'sent' => false]);

    $this->actingAs($user)
        ->get(route('admin.reminders.index', ['sent' => 'sent']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('reminders.data', 1)
            ->where('reminders.data.0.sent', true)
        );
});

test('reminders index filters by type', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $message = Message::factory()->create();
    $task = Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
    ]);
    Reminder::factory()->create(['task_id' => $task->id, 'type' => 'preparation']);
    Reminder::factory()->create(['task_id' => $task->id, 'type' => 'final']);

    $this->actingAs($user)
        ->get(route('admin.reminders.index', ['type' => 'final']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('reminders.data', 1)
            ->where('reminders.data.0.type', 'final')
        );
});

test('reminder show renders the reminder with task and message', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $message = Message::factory()->create(['summary' => 'Bring TL']);
    $task = Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
        'description' => 'Bring 350 TL',
    ]);
    $reminder = Reminder::factory()->create([
        'task_id' => $task->id,
        'message' => 'Pack 350 TL in backpack',
    ]);

    $this->actingAs($user)
        ->get(route('admin.reminders.show', $reminder))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Reminders/Show')
            ->where('reminder.id', $reminder->id)
            ->where('reminder.text', 'Pack 350 TL in backpack')
            ->where('reminder.task.id', $task->id)
            ->where('reminder.task.message.id', $message->id)
        );
});

test('reminder show 404s for unknown id', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/admin/reminders/9999')
        ->assertNotFound();
});
