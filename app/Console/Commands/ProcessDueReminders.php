<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\Reminders\ReminderNotifier;
use Illuminate\Console\Command;

class ProcessDueReminders extends Command
{
    protected $signature = 'reminders:process';

    protected $description = 'Send due reminder messages on each task\'s resolved channel';

    public function handle(ReminderNotifier $notifier): int
    {
        $dueReminders = Reminder::query()
            ->with('task')
            ->where('sent', false)
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($dueReminders as $reminder) {
            $task = $reminder->task;

            // Finished work no longer nudges on any channel, but the reminder is
            // still retired so it is not reconsidered.
            if ($task === null || in_array($task->status, ['completed', 'cancelled'], true)) {
                $reminder->update(['sent' => true, 'sent_at' => now()]);

                continue;
            }

            $notifier->sendReminder($task, $reminder->message);

            $reminder->update(['sent' => true, 'sent_at' => now()]);
        }

        $this->info("Processed {$dueReminders->count()} reminder(s).");

        return self::SUCCESS;
    }
}
