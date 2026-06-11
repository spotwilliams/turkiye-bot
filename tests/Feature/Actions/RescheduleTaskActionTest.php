<?php

use App\Actions\RescheduleTask;
use App\Models\Reminder;
use App\Models\Task;

test('reschedule persists the new due date and regenerates unsent reminders', function () {
    $task = Task::factory()->create([
        'category' => 'money',
        'due_date' => '2026-01-20',
        'telegram_chat_id' => 555,
    ]);
    Reminder::factory()->create([
        'task_id' => $task->id,
        'sent' => false,
        'scheduled_at' => '2026-01-19 20:00:00',
    ]);

    (new RescheduleTask)->execute($task, '2026-01-27');

    $task->refresh();
    expect($task->due_date->toDateString())->toBe('2026-01-27');

    $times = $task->reminders->pluck('scheduled_at')
        ->map(fn ($t) => $t->format('Y-m-d H:i'))->sort()->values()->all();
    expect($times)->toBe(['2026-01-26 20:00', '2026-01-27 07:30']);
});

test('reschedule keeps already-sent reminders as history', function () {
    $task = Task::factory()->create(['category' => 'money', 'due_date' => '2026-01-20']);
    $sent = Reminder::factory()->create([
        'task_id' => $task->id,
        'sent' => true,
        'scheduled_at' => '2026-01-19 20:00:00',
    ]);
    Reminder::factory()->create([
        'task_id' => $task->id,
        'sent' => false,
        'scheduled_at' => '2026-01-20 07:30:00',
    ]);

    (new RescheduleTask)->execute($task, '2026-01-27');

    expect(Reminder::find($sent->id))->not->toBeNull();
    expect($task->fresh()->reminders)->toHaveCount(3); // 1 sent kept + 2 regenerated
});

test('reschedule refuses a finished task and leaves it untouched', function (string $status) {
    $task = Task::factory()->create([
        'status' => $status,
        'category' => 'money',
        'due_date' => '2026-01-20',
    ]);

    expect(fn () => (new RescheduleTask)->execute($task, '2026-01-27'))
        ->toThrow(DomainException::class);

    expect($task->fresh()->due_date->toDateString())->toBe('2026-01-20');
})->with(['completed', 'cancelled']);
