<?php

use App\Models\Reminder;
use App\Models\User;

test('the snooze endpoint moves a reminder to a new time', function () {
    $reminder = Reminder::factory()->create([
        'scheduled_at' => '2026-01-20 07:30:00',
        'sent' => false,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->patch(route('web.reminders.snooze', $reminder), ['scheduled_at' => '2026-01-20 09:00'])
        ->assertRedirect();

    expect($reminder->fresh()->scheduled_at->format('Y-m-d H:i'))->toBe('2026-01-20 09:00');
});

test('the snooze endpoint requires a scheduled time', function () {
    $reminder = Reminder::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->patch(route('web.reminders.snooze', $reminder), [])
        ->assertSessionHasErrors('scheduled_at');
});

test('the snooze endpoint requires authentication', function () {
    $reminder = Reminder::factory()->create();

    $this->patch(route('web.reminders.snooze', $reminder), ['scheduled_at' => '2026-01-20 09:00'])
        ->assertRedirect(route('login'));
});
