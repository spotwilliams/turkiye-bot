<?php

use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;

function actingUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

test('task write endpoints require authentication', function () {
    $task = Task::factory()->create();

    $this->patch(route('web.tasks.complete', $task))->assertRedirect(route('login'));
    $this->patch(route('web.tasks.reschedule', $task), ['due_date' => '2026-01-27'])->assertRedirect(route('login'));
    $this->patch(route('web.tasks.update', $task), ['description' => 'x'])->assertRedirect(route('login'));
    $this->delete(route('web.tasks.destroy', $task))->assertRedirect(route('login'));

    expect($task->fresh()->status)->toBe('pending');
});

test('the done endpoint completes a task', function () {
    $task = Task::factory()->create(['telegram_chat_id' => null, 'status' => 'pending']);

    $this->actingAs(actingUser())
        ->patch(route('web.tasks.complete', $task))
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('completed');
});

test('the done endpoint is idempotent on an already-completed task', function () {
    $task = Task::factory()->create(['status' => 'completed', 'completed_at' => now()]);

    $this->actingAs(actingUser())
        ->patch(route('web.tasks.complete', $task))
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('completed');
});

test('the reschedule endpoint moves the due date and regenerates reminders', function () {
    $task = Task::factory()->create([
        'category' => 'money',
        'due_date' => '2026-01-20',
        'status' => 'pending',
    ]);

    $this->actingAs(actingUser())
        ->patch(route('web.tasks.reschedule', $task), ['due_date' => '2026-01-27'])
        ->assertRedirect();

    $task->refresh();
    expect($task->due_date->toDateString())->toBe('2026-01-27');
    $times = $task->reminders->pluck('scheduled_at')->map(fn ($t) => $t->format('Y-m-d H:i'))->sort()->values()->all();
    expect($times)->toBe(['2026-01-26 20:00', '2026-01-27 07:30']);
});

test('the reschedule endpoint rejects a missing due date', function () {
    $task = Task::factory()->create(['status' => 'pending']);

    $this->actingAs(actingUser())
        ->patch(route('web.tasks.reschedule', $task), [])
        ->assertSessionHasErrors('due_date');
});

test('the update endpoint edits task fields and regenerates reminders on category change', function () {
    $task = Task::factory()->create([
        'category' => 'item',
        'due_date' => '2026-01-20',
        'description' => 'old',
        'status' => 'pending',
    ]);

    $this->actingAs(actingUser())
        ->patch(route('web.tasks.update', $task), [
            'description' => 'Buy red shirt',
            'category' => 'homework',
        ])
        ->assertRedirect();

    $task->refresh();
    expect($task->description)->toBe('Buy red shirt');
    expect($task->category)->toBe('homework');
    $times = $task->reminders->pluck('scheduled_at')->map(fn ($t) => $t->format('Y-m-d H:i'))->sort()->values()->all();
    expect($times)->toBe(['2026-01-17 10:00', '2026-01-18 18:00']);
});

test('the destroy endpoint soft-cancels the task and keeps the row', function () {
    $task = Task::factory()->create(['status' => 'pending']);
    Reminder::factory()->create(['task_id' => $task->id, 'sent' => false]);

    $this->actingAs(actingUser())
        ->delete(route('web.tasks.destroy', $task))
        ->assertRedirect();

    $task->refresh();
    expect($task->status)->toBe('cancelled');
    expect(Task::find($task->id))->not->toBeNull();
    expect($task->reminders()->where('sent', false)->count())->toBe(0);
});
