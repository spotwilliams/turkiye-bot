<?php

namespace App\Actions;

use App\Models\Reminder;
use DateTimeInterface;

class SnoozeReminder
{
    /**
     * Move a single reminder to a later time. If it had already been sent, it
     * is reset so it fires again at the new time.
     */
    public function execute(Reminder $reminder, DateTimeInterface|string $scheduledAt): Reminder
    {
        $reminder->update([
            'scheduled_at' => $scheduledAt,
            'sent' => false,
            'sent_at' => null,
        ]);

        return $reminder;
    }
}
