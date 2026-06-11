<?php

namespace App\Services\Reminders;

use App\Models\Task;
use Illuminate\Support\Facades\Log;

class ReminderNotifier
{
    public function __construct(
        private TelegramChannel $telegram,
        private MailChannel $mail,
    ) {}

    /**
     * Resolve the delivery channel from the task's data and send a reminder:
     * a Telegram chat id routes to Telegram, otherwise a web creator routes to
     * email. A task with neither is logged and dropped (should not occur).
     */
    public function sendReminder(Task $task, string $message): void
    {
        $channel = $this->resolve($task);

        if ($channel === null) {
            Log::warning('Reminder has no deliverable channel.', ['task_id' => $task->id]);

            return;
        }

        $channel->sendReminder($task, $message);
    }

    private function resolve(Task $task): ?ReminderChannel
    {
        if ($task->telegram_chat_id !== null) {
            return $this->telegram;
        }

        if ($task->created_by !== null) {
            return $this->mail;
        }

        return null;
    }
}
