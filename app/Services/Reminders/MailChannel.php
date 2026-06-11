<?php

namespace App\Services\Reminders;

use App\Mail\TaskReminderMail;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailChannel implements ReminderChannel
{
    public function sendReminder(Task $task, string $message): void
    {
        $email = $this->creatorEmail($task);

        if ($email === null) {
            Log::warning('Mail reminder has no creator email.', ['task_id' => $task->id]);

            return;
        }

        Mail::to($email)->queue(new TaskReminderMail($task, $message));
    }

    private function creatorEmail(Task $task): ?string
    {
        return $task->created_by !== null
            ? User::find($task->created_by)?->email
            : null;
    }
}
