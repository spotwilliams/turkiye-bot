<?php

use App\Actions\GenerateTaskReminders;
use App\Models\Task;

function reminderFor(array $overrides): array
{
    $task = Task::factory()->make(['message_id' => 1, ...$overrides]);

    return (new GenerateTaskReminders)->for($task);
}

test('a money task generates evening-before prep and morning-of action reminders', function () {
    $reminders = reminderFor([
        'category' => 'money',
        'due_date' => '2026-01-20', // Tuesday
        'amount' => 350,
    ]);

    expect($reminders)->toHaveCount(2);

    expect($reminders[0]['type'])->toBe('preparation');
    expect($reminders[0]['scheduled_at']->format('Y-m-d H:i'))->toBe('2026-01-19 20:00');

    expect($reminders[1]['type'])->toBe('action');
    expect($reminders[1]['scheduled_at']->format('Y-m-d H:i'))->toBe('2026-01-20 07:30');
});

test('a homework task generates Saturday 10:00 prep and Sunday 18:00 final for the weekend before', function () {
    $reminders = reminderFor([
        'category' => 'homework',
        'due_date' => '2026-01-19', // Monday
    ]);

    expect($reminders)->toHaveCount(2);
    expect($reminders[0]['type'])->toBe('preparation');
    expect($reminders[0]['scheduled_at']->format('Y-m-d H:i'))->toBe('2026-01-17 10:00');
    expect($reminders[1]['type'])->toBe('final');
    expect($reminders[1]['scheduled_at']->format('Y-m-d H:i'))->toBe('2026-01-18 18:00');
});

test('an event task generates two-days-before, evening-before and morning-of reminders', function () {
    $reminders = reminderFor([
        'category' => 'event',
        'due_date' => '2026-01-20', // Tuesday
    ]);

    expect($reminders)->toHaveCount(3);
    expect($reminders[0]['type'])->toBe('preparation');
    expect($reminders[0]['scheduled_at']->format('Y-m-d H:i'))->toBe('2026-01-18 20:00');
    expect($reminders[1]['type'])->toBe('action');
    expect($reminders[1]['scheduled_at']->format('Y-m-d H:i'))->toBe('2026-01-19 20:00');
    expect($reminders[2]['type'])->toBe('final');
    expect($reminders[2]['scheduled_at']->format('Y-m-d H:i'))->toBe('2026-01-20 07:30');
});

test('the other category generates no reminders', function () {
    expect(reminderFor(['category' => 'other', 'due_date' => '2026-01-20']))->toHaveCount(0);
});

test('generated timestamps are built in the application timezone', function () {
    $reminders = reminderFor(['category' => 'money', 'due_date' => '2026-01-20']);

    foreach ($reminders as $reminder) {
        expect($reminder['scheduled_at']->timezoneName)->toBe(config('app.timezone'));
    }
});

test('an item task generates the same evening-before and morning-of anchors', function () {
    $reminders = reminderFor([
        'category' => 'item',
        'due_date' => '2026-01-20',
    ]);

    expect($reminders)->toHaveCount(2);
    expect($reminders[0]['type'])->toBe('preparation');
    expect($reminders[0]['scheduled_at']->format('Y-m-d H:i'))->toBe('2026-01-19 20:00');
    expect($reminders[1]['type'])->toBe('action');
    expect($reminders[1]['scheduled_at']->format('Y-m-d H:i'))->toBe('2026-01-20 07:30');
});
