<?php

use App\Jobs\ProcessSchoolMessage;
use App\Models\Message;
use App\Models\Reminder;
use App\Models\Task;
use App\Services\SchoolMessageProcessor;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

test('job persists message and sends confirmation', function () {
    Log::spy();

    $processor = Mockery::mock(SchoolMessageProcessor::class);
    $processor->shouldReceive('process')
        ->once()
        ->andReturn([
            'translation_en' => 'EN',
            'translation_es' => 'ES',
            'summary' => 'Summary',
            'tasks' => [],
        ]);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendProcessedConfirmation')
        ->once()
        ->withArgs(function (int $chatId, Message $message, int $taskCount): bool {
            return $chatId === 123
                && $taskCount === 0
                && $message->telegram_chat_id === 123
                && $message->telegram_message_id === 99
                && $message->translation_en === 'EN';
        })
        ->andReturnTrue();

    (new ProcessSchoolMessage('Merhaba', 123, 99))->handle($processor, $telegram);

    expect(Message::query()->count())->toBe(1);
    expect(Task::query()->count())->toBe(0);
});

test('job creates tasks and reminders from processor output', function () {
    $processor = Mockery::mock(SchoolMessageProcessor::class);
    $processor->shouldReceive('process')
        ->once()
        ->andReturn([
            'translation_en' => 'EN',
            'translation_es' => 'ES',
            'summary' => 'Summary',
            'tasks' => [[
                'description' => 'Bring 350 TL',
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
        ]);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendProcessedConfirmation')->once()->andReturnTrue();

    (new ProcessSchoolMessage('...', 555, 777))->handle($processor, $telegram);

    expect(Message::query()->count())->toBe(1);
    expect(Task::query()->count())->toBe(1);
    expect(Reminder::query()->count())->toBe(2);

    $task = Task::query()->firstOrFail();
    expect($task->telegram_chat_id)->toBe(555);
    expect($task->amount)->toBe('350.00');
    expect($task->reminders)->toHaveCount(2);
});
