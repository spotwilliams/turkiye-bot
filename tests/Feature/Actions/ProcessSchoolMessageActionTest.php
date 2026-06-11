<?php

use App\Actions\ProcessSchoolMessage;
use App\Ai\Agents\SchoolMessageProcessor;
use App\Models\Message;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;

test('action persists message and tasks, and generates reminders deterministically in PHP', function () {
    SchoolMessageProcessor::fake([
        [
            'translation_en' => 'Bring 350 TL for the theater trip.',
            'translation_es' => 'Traigan 350 TL para la excursión al teatro.',
            'summary' => 'Money needed for theater trip.',
            'tasks' => [[
                'description' => 'Bring 350 TL for theater trip',
                'category' => 'money',
                'due_date' => '2026-01-14',
                'due_time' => null,
                'amount' => 350,
                'currency' => 'TRY',
            ]],
        ],
    ]);

    $row = Message::factory()->create([
        'telegram_chat_id' => 555,
        'telegram_message_id' => 777,
        'original_text' => 'Değerli veliler...',
        'processed_at' => null,
    ]);
    $message = (new ProcessSchoolMessage)->execute($row);

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->telegram_chat_id)->toBe(555)
        ->and($message->translation_en)->toContain('350 TL')
        ->and($message->tasks)->toHaveCount(1)
        ->and($message->tasks->first()->reminders)->toHaveCount(2);

    // Reminders come from GenerateTaskReminders, not the agent: money task →
    // evening-before 20:00 (preparation) + morning-of 07:30 (action).
    $reminders = $message->tasks->first()->reminders->sortBy('scheduled_at')->values();
    expect($reminders[0]->type)->toBe('preparation');
    expect($reminders[0]->scheduled_at->format('Y-m-d H:i'))->toBe('2026-01-13 20:00');
    expect($reminders[1]->type)->toBe('action');
    expect($reminders[1]->scheduled_at->format('Y-m-d H:i'))->toBe('2026-01-14 07:30');

    expect(Message::query()->count())->toBe(1);
    expect(Task::query()->count())->toBe(1);
    expect(Reminder::query()->count())->toBe(2);
});

test('action stamps created_by on every task when a creator is given', function () {
    SchoolMessageProcessor::fake([
        [
            'translation_en' => 'Bring 350 TL.',
            'translation_es' => 'Traigan 350 TL.',
            'summary' => 'Money.',
            'tasks' => [[
                'description' => 'Bring 350 TL',
                'category' => 'money',
                'due_date' => '2026-01-14',
                'due_time' => null,
                'amount' => 350,
                'currency' => 'TRY',
            ]],
        ],
    ]);

    $user = User::factory()->create();
    $row = Message::factory()->create([
        'telegram_chat_id' => null,
        'processed_at' => null,
    ]);

    $message = (new ProcessSchoolMessage)->execute($row, $user->id);

    expect($message->tasks->first()->created_by)->toBe($user->id);
});

test('action handles informational messages with no tasks', function () {
    SchoolMessageProcessor::fake([
        [
            'translation_en' => 'School closed tomorrow.',
            'translation_es' => 'Escuela cerrada mañana.',
            'summary' => 'Closure notice.',
            'tasks' => [],
        ],
    ]);

    $row = Message::factory()->create([
        'telegram_chat_id' => 1,
        'telegram_message_id' => 2,
        'original_text' => 'Okul yarın kapalı.',
        'processed_at' => null,
    ]);
    $message = (new ProcessSchoolMessage)->execute($row);

    expect($message->tasks)->toHaveCount(0);
    expect(Task::query()->count())->toBe(0);
});
