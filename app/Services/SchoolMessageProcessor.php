<?php

namespace App\Services;

use App\Ai\Agents\SchoolMessageProcessor as SchoolMessageProcessorAgent;

class SchoolMessageProcessor
{
    /**
     * Run the school-message AI agent and return its structured output.
     *
     * @return array{
     *     translation_en: string,
     *     translation_es: string,
     *     summary: string,
     *     tasks: array<int, array{
     *         description: string,
     *         category: string,
     *         due_date: string,
     *         due_time: string|null,
     *         amount: float|null,
     *         currency: string|null,
     *         reminders: array<int, array{scheduled_at: string, message: string, type: string}>
     *     }>
     * }
     */
    public function process(string $message): array
    {
        $response = (new SchoolMessageProcessorAgent)->prompt($message);

        return [
            'translation_en' => (string) $response['translation_en'],
            'translation_es' => (string) $response['translation_es'],
            'summary' => (string) $response['summary'],
            'tasks' => $this->normalizeTasks($response['tasks'] ?? []),
        ];
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $tasks
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTasks(iterable $tasks): array
    {
        $normalized = [];

        foreach ($tasks as $task) {
            $normalized[] = [
                'description' => (string) ($task['description'] ?? ''),
                'category' => (string) ($task['category'] ?? 'other'),
                'due_date' => (string) ($task['due_date'] ?? ''),
                'due_time' => $task['due_time'] ?? null,
                'amount' => isset($task['amount']) ? (float) $task['amount'] : null,
                'currency' => $task['currency'] ?? 'TRY',
                'reminders' => $this->normalizeReminders($task['reminders'] ?? []),
            ];
        }

        return $normalized;
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $reminders
     * @return array<int, array{scheduled_at: string, message: string, type: string}>
     */
    private function normalizeReminders(iterable $reminders): array
    {
        $normalized = [];

        foreach ($reminders as $reminder) {
            $normalized[] = [
                'scheduled_at' => (string) ($reminder['scheduled_at'] ?? ''),
                'message' => (string) ($reminder['message'] ?? ''),
                'type' => (string) ($reminder['type'] ?? 'action'),
            ];
        }

        return $normalized;
    }
}
