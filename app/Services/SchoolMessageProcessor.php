<?php

namespace App\Services;

use Illuminate\Support\Str;

class SchoolMessageProcessor
{
    /**
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
        $normalizedMessage = trim($message);
        $summary = Str::of($normalizedMessage)->squish()->limit(180)->toString();

        // Phase 1 fallback output while AI extraction is not wired.
        return [
            'translation_en' => $normalizedMessage,
            'translation_es' => $normalizedMessage,
            'summary' => $summary,
            'tasks' => [],
        ];
    }
}
