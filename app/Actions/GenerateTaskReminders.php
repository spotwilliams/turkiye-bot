<?php

namespace App\Actions;

use App\Models\Task;
use Illuminate\Support\Carbon;

class GenerateTaskReminders
{
    /**
     * Build the deterministic reminder set for a task from its category and
     * due date. Generation only — the caller decides persistence.
     *
     * @return list<array{scheduled_at: Carbon, message: string, type: string}>
     */
    public function for(Task $task): array
    {
        $dueDate = $task->due_date instanceof \DateTimeInterface
            ? $task->due_date->format('Y-m-d')
            : (string) $task->due_date;

        $due = Carbon::parse($dueDate, config('app.timezone'))->startOfDay();

        return match ($task->category) {
            'money', 'item' => $this->eveningBeforeAndMorningOf($due),
            'homework' => $this->weekendBefore($due),
            'event' => $this->eventLeadUp($due),
            default => [],
        };
    }

    /**
     * @return list<array{scheduled_at: Carbon, message: string, type: string}>
     */
    private function eventLeadUp(Carbon $due): array
    {
        return [
            [
                'scheduled_at' => $due->copy()->subDays(2)->setTime(20, 0),
                'message' => 'Upcoming event in two days.',
                'type' => 'preparation',
            ],
            [
                'scheduled_at' => $due->copy()->subDay()->setTime(20, 0),
                'message' => 'Event is tomorrow.',
                'type' => 'action',
            ],
            [
                'scheduled_at' => $due->copy()->setTime(7, 30),
                'message' => 'Event is today.',
                'type' => 'final',
            ],
        ];
    }

    /**
     * @return list<array{scheduled_at: Carbon, message: string, type: string}>
     */
    private function weekendBefore(Carbon $due): array
    {
        $saturday = $due->copy()->previous(Carbon::SATURDAY);

        return [
            [
                'scheduled_at' => $saturday->copy()->setTime(10, 0),
                'message' => 'Start the homework.',
                'type' => 'preparation',
            ],
            [
                'scheduled_at' => $saturday->copy()->addDay()->setTime(18, 0),
                'message' => 'Check the homework is done.',
                'type' => 'final',
            ],
        ];
    }

    /**
     * @return list<array{scheduled_at: Carbon, message: string, type: string}>
     */
    private function eveningBeforeAndMorningOf(Carbon $due): array
    {
        return [
            [
                'scheduled_at' => $due->copy()->subDay()->setTime(20, 0),
                'message' => 'Prepare for tomorrow.',
                'type' => 'preparation',
            ],
            [
                'scheduled_at' => $due->copy()->setTime(7, 30),
                'message' => 'Pack it in the backpack today.',
                'type' => 'action',
            ],
        ];
    }
}
