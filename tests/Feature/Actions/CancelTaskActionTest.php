<?php

use App\Actions\CancelTask;
use App\Models\Reminder;
use App\Models\Task;

test('cancel sets status to cancelled, wipes unsent reminders, and keeps the row', function () {
    $task = Task::factory()->create(['status' => 'pending']);
    Reminder::factory()->create(['task_id' => $task->id, 'sent' => false]);
    $sent = Reminder::factory()->create(['task_id' => $task->id, 'sent' => true]);

    (new CancelTask)->execute($task);

    $task->refresh();
    expect($task->status)->toBe('cancelled');
    expect(Task::find($task->id))->not->toBeNull();
    expect($task->reminders->pluck('id')->all())->toBe([$sent->id]);
});

test('cancelling an already-cancelled task is a safe no-op', function () {
    $task = Task::factory()->create(['status' => 'cancelled']);

    (new CancelTask)->execute($task);

    expect($task->fresh()->status)->toBe('cancelled');
});
