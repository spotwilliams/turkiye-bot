<?php

namespace App\Actions;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

class CompleteTaskResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?Task $task = null,
        public readonly int $remaining = 0,
    ) {}
}

class CompleteTask
{
    public function execute(int $taskId, int $chatId): CompleteTaskResult
    {
        return DB::transaction(function () use ($taskId, $chatId): CompleteTaskResult {
            $task = Task::where('id', $taskId)
                ->where('telegram_chat_id', $chatId)
                ->lockForUpdate()
                ->first();

            if ($task === null) {
                return new CompleteTaskResult('not_found');
            }

            if ($task->status === 'completed') {
                return new CompleteTaskResult('already_done', $task);
            }

            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $remaining = Task::where('telegram_chat_id', $chatId)
                ->where('status', 'pending')
                ->count();

            return new CompleteTaskResult('completed', $task, $remaining);
        });
    }
}
