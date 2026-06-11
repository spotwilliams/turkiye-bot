<?php

namespace App\Actions;

use App\Models\Task;

class RegenerateTaskReminders
{
    public function __construct(
        private GenerateTaskReminders $generateReminders = new GenerateTaskReminders,
    ) {}

    /**
     * Apply the regeneration contract for a task whose due date or category
     * changed: wipe unsent reminders, keep sent ones as history, and rebuild
     * the unsent set from the deterministic generator.
     */
    public function execute(Task $task): void
    {
        $task->reminders()->where('sent', false)->delete();

        foreach ($this->generateReminders->for($task) as $reminder) {
            $task->reminders()->create($reminder);
        }
    }
}
