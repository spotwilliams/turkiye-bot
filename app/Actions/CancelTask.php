<?php

namespace App\Actions;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

class CancelTask
{
    /**
     * Soft-cancel a task: mark it cancelled and wipe its unsent reminders while
     * keeping the row (and sent reminders) as history. Idempotent on an
     * already-cancelled task.
     */
    public function execute(Task $task): Task
    {
        if ($task->status === 'cancelled') {
            return $task;
        }

        return DB::transaction(function () use ($task): Task {
            $task->update(['status' => 'cancelled']);
            $task->reminders()->where('sent', false)->delete();

            return $task->load('reminders');
        });
    }
}
