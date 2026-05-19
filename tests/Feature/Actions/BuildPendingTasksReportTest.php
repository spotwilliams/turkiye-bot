<?php

use App\Actions\BuildPendingTasksReport;
use App\Models\Message;
use App\Models\Task;
use Carbon\CarbonImmutable;

test('report returns friendly empty state when no pending tasks exist', function () {
    $report = (new BuildPendingTasksReport)->execute(999, CarbonImmutable::parse('2026-04-24 09:00:00'));

    expect($report)->toBe("No pending tasks. You're all set!");
});

test('report groups tasks by source message and includes metadata', function () {
    $msg1 = Message::factory()->create([
        'telegram_chat_id' => 100,
        'summary' => 'Field trip info and red t-shirt request.',
    ]);
    $msg2 = Message::factory()->create([
        'telegram_chat_id' => 100,
        'summary' => 'Math homework assignment.',
    ]);

    Task::factory()->create([
        'id' => 123,
        'message_id' => $msg1->id,
        'telegram_chat_id' => 100,
        'description' => 'Bring 350 TL for field trip',
        'due_date' => '2026-04-26',
        'assigned_to' => 'father',
        'status' => 'pending',
    ]);
    Task::factory()->create([
        'id' => 124,
        'message_id' => $msg1->id,
        'telegram_chat_id' => 100,
        'description' => 'Bring red t-shirt',
        'due_date' => '2026-04-26',
        'assigned_to' => 'both',
        'status' => 'pending',
    ]);
    Task::factory()->create([
        'id' => 125,
        'message_id' => $msg2->id,
        'telegram_chat_id' => 100,
        'description' => 'Math homework pages 45-48',
        'due_date' => '2026-04-20',
        'assigned_to' => 'mother',
        'status' => 'pending',
    ]);

    $report = (new BuildPendingTasksReport)->execute(100, CarbonImmutable::parse('2026-04-24 09:00:00'));

    expect($report)
        ->toContain('Field trip info and red t-shirt request.')
        ->toContain('Math homework assignment.')
        ->toContain('#123  Bring 350 TL for field trip')
        ->toContain('#124  Bring red t-shirt')
        ->toContain('#125  Math homework pages 45-48')
        ->toContain('Due date: 26/04/26')
        ->toContain('remaining')
        ->toContain('delayed by')
        ->toContain('Who: father')
        ->toContain('Who: both')
        ->toContain('Who: mother');
});

test('report only includes pending tasks for the requested chat and unassigned fallback', function () {
    $mine = Message::factory()->create([
        'telegram_chat_id' => 1,
        'summary' => 'Mine summary',
    ]);
    $other = Message::factory()->create([
        'telegram_chat_id' => 2,
        'summary' => 'Other summary',
    ]);

    Task::factory()->create([
        'message_id' => $mine->id,
        'telegram_chat_id' => 1,
        'description' => 'my pending task',
        'assigned_to' => '',
        'due_date' => '2026-05-01',
        'status' => 'pending',
    ]);
    Task::factory()->create([
        'message_id' => $mine->id,
        'telegram_chat_id' => 1,
        'description' => 'my completed task',
        'due_date' => '2026-05-01',
        'status' => 'completed',
    ]);
    Task::factory()->create([
        'message_id' => $other->id,
        'telegram_chat_id' => 2,
        'description' => 'other chat task',
        'due_date' => '2026-05-01',
        'status' => 'pending',
    ]);

    $report = (new BuildPendingTasksReport)->execute(1, CarbonImmutable::parse('2026-04-24 09:00:00'));

    expect($report)
        ->toContain('my pending task')
        ->toContain('Who: unassigned')
        ->not->toContain('my completed task')
        ->not->toContain('other chat task');
});
