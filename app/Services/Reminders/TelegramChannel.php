<?php

namespace App\Services\Reminders;

use App\Models\Task;
use App\Services\TelegramService;

class TelegramChannel implements ReminderChannel
{
    public function __construct(private TelegramService $telegram) {}

    public function sendReminder(Task $task, string $message): void
    {
        $this->telegram->sendMessage((int) $task->telegram_chat_id, "Reminder: {$message}");
    }
}
