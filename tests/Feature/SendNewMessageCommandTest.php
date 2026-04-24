<?php

use App\Ai\Agents\SchoolMessageProcessor;
use App\Models\Message;
use App\Models\Reminder;
use App\Models\Task;

function fakeAgentWithTask(): void
{
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
}

test('command runs action, persists records, and prints a summary', function () {
    fakeAgentWithTask();

    $this->artisan('message:new', [
        'text' => 'Değerli veliler...',
        '--chat-id' => 123,
        '--message-id' => 456,
    ])
        ->expectsOutputToContain('Bring 350 TL for theater trip')
        ->assertSuccessful();

    expect(Message::query()->count())->toBe(1);
    expect(Task::query()->count())->toBe(1);
    expect(Reminder::query()->count())->toBe(2);
});

test('command fails when no text and no file provided', function () {
    $this->artisan('message:new')->assertFailed();

    expect(Message::query()->count())->toBe(0);
});

test('command rejects non numeric chat id', function () {
    $this->artisan('message:new', [
        'text' => 'hello',
        '--chat-id' => 'abc',
    ])->assertFailed();
});

test('command loads text from file with --from-file', function () {
    fakeAgentWithTask();

    $path = tempnam(sys_get_temp_dir(), 'msg');
    file_put_contents($path, "Değerli veliler...\n");

    $this->artisan('message:new', [
        '--from-file' => $path,
        '--chat-id' => 1,
        '--message-id' => 2,
    ])->assertSuccessful();

    @unlink($path);

    expect(Message::query()->where('telegram_chat_id', 1)->count())->toBe(1);
});

test('command fails when --from-file points to missing file', function () {
    $this->artisan('message:new', [
        '--from-file' => '/tmp/definitely-not-a-real-file-'.uniqid(),
    ])->assertFailed();
});

test('--json prints structured output with persisted message', function () {
    fakeAgentWithTask();

    $this->artisan('message:new', [
        'text' => 'Değerli veliler...',
        '--chat-id' => 7,
        '--message-id' => 8,
        '--json' => true,
    ])
        ->expectsOutputToContain('"telegram_chat_id":7')
        ->assertSuccessful();
});
