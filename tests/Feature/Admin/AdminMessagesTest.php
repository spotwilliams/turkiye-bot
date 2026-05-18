<?php

use App\Models\Message;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;

test('unauthenticated users are redirected from messages index', function () {
    $this->get(route('admin.messages.index'))->assertRedirect();
});

test('unauthenticated users are redirected from message show', function () {
    $message = Message::factory()->create();
    $this->get(route('admin.messages.show', $message))->assertRedirect();
});

test('messages index renders paginated messages with task counts', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Message::factory()->count(3)->create()->each(function (Message $message) {
        Task::factory()->count(2)->create([
            'message_id' => $message->id,
            'telegram_chat_id' => $message->telegram_chat_id,
        ]);
    });

    $this->actingAs($user)
        ->get(route('admin.messages.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Messages/Index')
            ->has('messages.data', 3)
            ->where('messages.data.0.tasks_count', 2)
            ->has('messages.data.0.ref')
        );
});

test('messages index filters by chat id', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Message::factory()->create(['telegram_chat_id' => 111]);
    Message::factory()->create(['telegram_chat_id' => 222]);

    $this->actingAs($user)
        ->get(route('admin.messages.index', ['chat_id' => 111]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('messages.data', 1)
            ->where('messages.data.0.telegram_chat_id', '111')
            ->where('filters.chat_id', '111')
        );
});

test('messages index filters by processed state', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Message::factory()->create(['processed_at' => now()]);
    Message::factory()->create(['processed_at' => null]);

    $this->actingAs($user)
        ->get(route('admin.messages.index', ['processed' => 'pending']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('messages.data', 1)
            ->where('messages.data.0.processed_at', null)
        );
});

test('messages index renders an empty paginator when there are no messages', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('admin.messages.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Messages/Index')
            ->has('messages.data', 0)
            ->where('messages.total', 0)
        );
});

test('message show renders the message with nested tasks and reminders', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $message = Message::factory()->create([
        'summary' => 'Trip on Friday',
        'translation_en' => 'English text',
        'translation_es' => 'Spanish text',
    ]);

    $task = Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
        'description' => 'Bring 350 TL',
        'category' => 'money',
        'status' => 'pending',
    ]);

    Reminder::factory()->count(2)->create([
        'task_id' => $task->id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.messages.show', $message))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Messages/Show')
            ->where('message.id', $message->id)
            ->where('message.summary', 'Trip on Friday')
            ->where('message.english', 'English text')
            ->where('message.spanish', 'Spanish text')
            ->has('message.tasks', 1)
            ->where('message.tasks.0.description', 'Bring 350 TL')
            ->has('message.tasks.0.reminders', 2)
        );
});

test('message show 404s for unknown message id', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/admin/messages/9999')
        ->assertNotFound();
});

test('message show handles a message with no tasks', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $message = Message::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.messages.show', $message))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Messages/Show')
            ->has('message.tasks', 0)
        );
});
