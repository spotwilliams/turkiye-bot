<?php

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-06-11 09:00:00');
});

test('the snooze endpoint moves a reminder to a new time', function () {
    $reminder = Reminder::factory()->create([
        'scheduled_at' => '2026-06-11 07:30:00',
        'sent' => false,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->patch(route('web.reminders.snooze', $reminder), ['scheduled_at' => '2026-06-11 11:00'])
        ->assertRedirect();

    expect($reminder->fresh()->scheduled_at->format('Y-m-d H:i'))->toBe('2026-06-11 11:00');
});

test('the snooze endpoint rejects a time in the past', function () {
    $reminder = Reminder::factory()->create(['scheduled_at' => '2026-06-11 07:30:00']);
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->patch(route('web.reminders.snooze', $reminder), ['scheduled_at' => '2026-06-10 09:00'])
        ->assertSessionHasErrors('scheduled_at');

    expect($reminder->fresh()->scheduled_at->format('Y-m-d H:i'))->toBe('2026-06-11 07:30');
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

    $this->patch(route('web.reminders.snooze', $reminder), ['scheduled_at' => '2026-06-11 11:00'])
        ->assertRedirect(route('login'));
});
