<?php

use App\Ai\Agents\SchoolMessageProcessor;
use Laravel\Ai\Prompts\AgentPrompt;

test('agent instructions embed current date and day of week', function () {
    $agent = new SchoolMessageProcessor('2026-04-23', 'Thursday');

    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('2026-04-23')
        ->and($instructions)->toContain('Thursday')
        ->and($instructions)->toContain('school message processing assistant');
});

test('agent defaults current date and day of week to now', function () {
    $agent = new SchoolMessageProcessor;

    expect($agent->currentDate)->toBe(now()->format('Y-m-d'))
        ->and($agent->dayOfWeek)->toBe(now()->format('l'));
});

test('agent returns structured output matching schema', function () {
    SchoolMessageProcessor::fake([
        [
            'translation_en' => 'Please bring 350 TL for the theater trip by Tuesday.',
            'translation_es' => 'Traigan 350 TL para la excursión al teatro antes del martes.',
            'summary' => 'Money needed for theater trip.',
            'tasks' => [[
                'description' => 'Bring 350 TL for theater trip',
                'category' => 'money',
                'due_date' => '2026-01-14',
                'due_time' => null,
                'amount' => 350,
                'currency' => 'TRY',
                'reminders' => [
                    ['scheduled_at' => '2026-01-13T20:00', 'message' => 'Prepare 350 TL', 'type' => 'preparation'],
                    ['scheduled_at' => '2026-01-14T07:30', 'message' => 'Put 350 TL in backpack', 'type' => 'action'],
                ],
            ]],
        ],
    ]);

    $response = (new SchoolMessageProcessor)->prompt('Değerli veliler...');

    expect($response['tasks'])->toHaveCount(1)
        ->and($response['tasks'][0]['category'])->toBe('money')
        ->and($response['tasks'][0]['amount'])->toBe(350)
        ->and($response['tasks'][0]['reminders'])->toHaveCount(2)
        ->and($response['translation_en'])->toContain('350 TL')
        ->and($response['translation_es'])->toContain('350 TL');

    SchoolMessageProcessor::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('Değerli veliler'));
});

test('agent returns empty tasks array for informational messages', function () {
    SchoolMessageProcessor::fake([
        [
            'translation_en' => 'School will be closed for the national holiday.',
            'translation_es' => 'La escuela estará cerrada por el día festivo nacional.',
            'summary' => 'Informational notice about a school closure.',
            'tasks' => [],
        ],
    ]);

    $response = (new SchoolMessageProcessor)->prompt('Okul yarın kapalı olacaktır.');

    expect($response['tasks'])->toBe([])
        ->and($response['summary'])->not->toBe('');
});
