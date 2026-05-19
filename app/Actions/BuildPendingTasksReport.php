<?php

namespace App\Actions;

use App\Models\Task;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BuildPendingTasksReport
{
    public function execute(int $chatId, ?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));

        /** @var Collection<int, Task> $tasks */
        $tasks = Task::query()
            ->with('message')
            ->where('telegram_chat_id', $chatId)
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->orderByRaw('due_time IS NULL, due_time')
            ->get();

        if ($tasks->isEmpty()) {
            return "No pending tasks. You're all set!";
        }

        $groups = $tasks->groupBy('message_id');

        $blocks = [];
        foreach ($groups as $tasksForMessage) {
            /** @var Task $first */
            $first = $tasksForMessage->first();
            $summary = $first->message->summary ?? '(unknown message)';

            $lines = ["📨 {$summary}"];
            foreach ($tasksForMessage as $task) {
                $lines[] = "  #{$task->id}  {$task->description}";
                $lines[] = '    Due date: '.$this->formatDue($task, $now);
                $lines[] = '    Who: '.$this->formatAssignee($task);
            }
            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    private function formatDue(Task $task, CarbonImmutable $now): string
    {
        $dueDate = CarbonImmutable::parse($task->due_date, config('app.timezone'));
        $due = $dueDate->setTimeFromTimeString($task->due_time ?? '23:59:59');

        $date = $due->format('d/m/y');

        if ($due->greaterThanOrEqualTo($now)) {
            $remaining = $now->diffForHumans($due, [
                'syntax' => CarbonInterface::DIFF_ABSOLUTE,
                'parts' => 1,
            ]);

            return "{$date} ({$remaining} remaining)";
        }

        $delayed = $due->diffForHumans($now, [
            'syntax' => CarbonInterface::DIFF_ABSOLUTE,
            'parts' => 1,
        ]);

        return "{$date} (delayed by {$delayed})";
    }

    private function formatAssignee(Task $task): string
    {
        $assigned = (string) ($task->assigned_to ?? 'both');

        return $assigned === '' ? 'unassigned' : $assigned;
    }
}
