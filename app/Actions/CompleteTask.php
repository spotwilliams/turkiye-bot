<?php

namespace App\Actions;

use App\Actions\Dto\CompleteTaskResult;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class CompleteTask
{
    public function execute(int $taskId, int $chatId): CompleteTaskResult
    {
        return DB::transaction(function () use ($taskId, $chatId): CompleteTaskResult {
            $task = Task::query()
                ->where('id', $taskId)
                ->where('telegram_chat_id', $chatId)
                ->lockForUpdate()
                ->first();

            if ($task === null) {
                return new CompleteTaskResult('not_found');
            }

            if ($task->status === 'completed') {
                return new CompleteTaskResult('already_done', $task);
            }

            $this->complete($task);

            $remaining = Task::where('telegram_chat_id', $chatId)
                ->where('status', 'pending')
                ->count();

            return new CompleteTaskResult('completed', $task, $remaining);
        });
    }

    /**
     * Mark a task complete, surface-agnostic and idempotent. Used by the web
     * surface where there is no chat to scope by (flat shared workspace).
     */
    public function complete(Task $task): Task
    {
        if ($task->status !== 'completed') {
            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return $task;
    }
}
