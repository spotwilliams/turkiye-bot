<?php

use App\Actions\SnoozeReminder;
use App\Models\Reminder;

test('snooze moves the reminder to the new scheduled time', function () {
    $reminder = Reminder::factory()->create([
        'scheduled_at' => '2026-01-20 07:30:00',
        'sent' => false,
    ]);

    (new SnoozeReminder)->execute($reminder, '2026-01-20 09:00:00');

    expect($reminder->fresh()->scheduled_at->format('Y-m-d H:i'))->toBe('2026-01-20 09:00');
});

test('snoozing an already-sent reminder resets it to fire again', function () {
    $reminder = Reminder::factory()->create([
        'sent' => true,
        'sent_at' => now(),
        'scheduled_at' => '2026-01-20 07:30:00',
    ]);

    (new SnoozeReminder)->execute($reminder, '2026-01-20 09:00:00');

    $reminder->refresh();
    expect($reminder->sent)->toBeFalse();
    expect($reminder->sent_at)->toBeNull();
    expect($reminder->scheduled_at->format('Y-m-d H:i'))->toBe('2026-01-20 09:00');
});
