<?php

use App\Models\Message;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;

test('unauthenticated users are redirected from tasks index', function () {
    $this->get(route('admin.tasks.index'))->assertRedirect();
});

test('unauthenticated users are redirected from task show', function () {
    $message = Message::factory()->create();
    $task = Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
    ]);

    $this->get(route('admin.tasks.show', $task))->assertRedirect();
});

test('tasks index renders paginated tasks with reminder counts', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $message = Message::factory()->create();

    Task::factory()->count(3)->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
    ])->each(function (Task $task) {
        Reminder::factory()->count(2)->create(['task_id' => $task->id]);
    });

    $this->actingAs($user)
        ->get(route('admin.tasks.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Tasks/Index')
            ->has('tasks.data', 3)
            ->where('tasks.data.0.reminders_count', 2)
            ->has('tasks.data.0.ref')
        );
});

test('tasks index filters by status', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $message = Message::factory()->create();

    Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
        'status' => 'pending',
    ]);
    Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
        'status' => 'completed',
    ]);

    $this->actingAs($user)
        ->get(route('admin.tasks.index', ['status' => 'completed']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.status', 'completed')
            ->where('filters.status', 'completed')
        );
});

test('tasks index filters by category', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $message = Message::factory()->create();

    Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
        'category' => 'money',
    ]);
    Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
        'category' => 'homework',
    ]);

    $this->actingAs($user)
        ->get(route('admin.tasks.index', ['category' => 'money']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.category', 'money')
        );
});

test('tasks index renders empty paginator when there are no tasks', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('admin.tasks.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Tasks/Index')
            ->has('tasks.data', 0)
            ->where('tasks.total', 0)
        );
});

test('task show renders the task with reminders and source message', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $message = Message::factory()->create(['summary' => 'Friday trip']);
    $task = Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
        'description' => 'Bring 350 TL',
        'category' => 'money',
        'status' => 'pending',
    ]);
    Reminder::factory()->count(2)->create(['task_id' => $task->id]);

    $this->actingAs($user)
        ->get(route('admin.tasks.show', $task))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Tasks/Show')
            ->where('task.id', $task->id)
            ->where('task.description', 'Bring 350 TL')
            ->where('task.message.id', $message->id)
            ->where('task.message.summary', 'Friday trip')
            ->has('task.reminders', 2)
        );
});

test('task show 404s for unknown task id', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/admin/tasks/9999')
        ->assertNotFound();
});

test('task show handles a task with no reminders', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $message = Message::factory()->create();
    $task = Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => $message->telegram_chat_id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.tasks.show', $task))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Tasks/Show')
            ->has('task.reminders', 0)
        );
});
