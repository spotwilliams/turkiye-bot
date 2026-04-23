<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ProcessDueReminders extends Command
{
    protected $signature = 'reminders:process';

    protected $description = 'Send due reminder messages';

    public function handle(TelegramService $telegram): int
    {
        $dueReminders = Reminder::query()
            ->with('task')
            ->where('sent', false)
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($dueReminders as $reminder) {
            if ($reminder->task->status === 'completed') {
                $reminder->update([
                    'sent' => true,
                    'sent_at' => now(),
                ]);

                continue;
            }

            $telegram->sendMessage(
                $reminder->task->telegram_chat_id,
                "Reminder: {$reminder->message}"
            );

            $reminder->update([
                'sent' => true,
                'sent_at' => now(),
            ]);
        }

        $this->info("Processed {$dueReminders->count()} reminder(s).");

        return self::SUCCESS;
    }
}
