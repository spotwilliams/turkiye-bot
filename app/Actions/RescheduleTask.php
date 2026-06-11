<?php

namespace App\Actions;

use App\Models\Task;
use DomainException;
use Illuminate\Support\Facades\DB;

class RescheduleTask
{
    public function __construct(
        private RegenerateTaskReminders $regenerateReminders = new RegenerateTaskReminders,
    ) {}

    /**
     * Move a task to an already-resolved due date (and optional time) and
     * regenerate its unsent reminders for the new date. Sent reminders are
     * kept as history.
     */
    public function execute(Task $task, string $dueDate, ?string $dueTime = null): Task
    {
        if (in_array($task->status, ['completed', 'cancelled'], true)) {
            throw new DomainException("Cannot reschedule a {$task->status} task.");
        }

        return DB::transaction(function () use ($task, $dueDate, $dueTime): Task {
            $task->update([
                'due_date' => $dueDate,
                'due_time' => $dueTime,
            ]);

            $this->regenerateReminders->execute($task);

            return $task->load('reminders');
        });
    }
}
