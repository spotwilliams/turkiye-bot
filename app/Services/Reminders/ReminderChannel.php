<?php

namespace App\Services\Reminders;

use App\Models\Task;

interface ReminderChannel
{
    /**
     * Deliver a single reminder for the given task on this channel.
     */
    public function sendReminder(Task $task, string $message): void;
}
