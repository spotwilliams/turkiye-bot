<?php

use App\Actions\ProcessSchoolMessage;
use App\Ai\Agents\SchoolMessageProcessor;
use App\Models\Message;
use App\Models\Reminder;
use App\Models\Task;

test('action persists message, tasks, and reminders from agent output', function () {
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
                'reminders' => [
                    ['scheduled_at' => '2026-01-13 20:00:00', 'message' => 'Prepare 350 TL', 'type' => 'preparation'],
                    ['scheduled_at' => '2026-01-14 07:30:00', 'message' => 'Put 350 TL in backpack', 'type' => 'action'],
                ],
            ]],
        ],
    ]);

    $message = (new ProcessSchoolMessage)->execute('Değerli veliler...', 555, 777);

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->telegram_chat_id)->toBe(555)
        ->and($message->telegram_message_id)->toBe(777)
        ->and($message->translation_en)->toContain('350 TL')
        ->and($message->tasks)->toHaveCount(1)
        ->and($message->tasks->first()->reminders)->toHaveCount(2);

    expect(Message::query()->count())->toBe(1);
    expect(Task::query()->count())->toBe(1);
    expect(Reminder::query()->count())->toBe(2);
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

    $message = (new ProcessSchoolMessage)->execute('Okul yarın kapalı.', 1, 2);

    expect($message->tasks)->toHaveCount(0);
    expect(Task::query()->count())->toBe(0);
});
