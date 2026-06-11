<?php

use App\Actions\EditTask;
use App\Models\Reminder;
use App\Models\Task;

test('editing the category regenerates reminders with the new pattern', function () {
    $task = Task::factory()->create(['category' => 'item', 'due_date' => '2026-01-20']);
    Reminder::factory()->create([
        'task_id' => $task->id,
        'sent' => false,
        'scheduled_at' => '2026-01-19 20:00:00',
    ]);

    (new EditTask)->execute($task, ['category' => 'homework']);

    $task->refresh();
    expect($task->category)->toBe('homework');

    $times = $task->reminders->pluck('scheduled_at')
        ->map(fn ($t) => $t->format('Y-m-d H:i'))->sort()->values()->all();
    expect($times)->toBe(['2026-01-17 10:00', '2026-01-18 18:00']);
});

test('editing only the description leaves reminders untouched', function () {
    $task = Task::factory()->create(['category' => 'money', 'due_date' => '2026-01-20', 'description' => 'old']);
    $r1 = Reminder::factory()->create(['task_id' => $task->id, 'sent' => false]);
    $r2 = Reminder::factory()->create(['task_id' => $task->id, 'sent' => false]);

    (new EditTask)->execute($task, ['description' => 'new description']);

    $task->refresh();
    expect($task->description)->toBe('new description');
    expect($task->reminders->pluck('id')->sort()->values()->all())->toBe([$r1->id, $r2->id]);
});
