<?php

namespace App\Actions;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

class EditTask
{
    private const EDITABLE = [
        'description', 'category', 'due_date', 'due_time', 'amount', 'currency', 'assigned_to',
    ];

    public function __construct(
        private RegenerateTaskReminders $regenerateReminders = new RegenerateTaskReminders,
    ) {}

    /**
     * Update task fields. When the due date or category changes, reminders are
     * regenerated for the new pattern; edits to other fields leave reminders
     * untouched.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Task $task, array $attributes): Task
    {
        $changes = array_intersect_key($attributes, array_flip(self::EDITABLE));

        return DB::transaction(function () use ($task, $changes): Task {
            $shouldRegenerate = $this->changesReminderShape($task, $changes);

            $task->update($changes);

            if ($shouldRegenerate) {
                $this->regenerateReminders->execute($task);
            }

            return $task->load('reminders');
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function changesReminderShape(Task $task, array $changes): bool
    {
        $categoryChanged = array_key_exists('category', $changes)
            && $changes['category'] !== $task->category;

        $dueDateChanged = array_key_exists('due_date', $changes)
            && (string) $changes['due_date'] !== $task->due_date->toDateString();

        return $categoryChanged || $dueDateChanged;
    }
}
