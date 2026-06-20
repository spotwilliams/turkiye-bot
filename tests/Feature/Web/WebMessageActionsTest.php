<?php

use App\Jobs\ProcessSchoolMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    Carbon::setTestNow('2026-06-11 09:00:00');
    Bus::fake();
});

test('retry clears failure state and re-dispatches the job', function () {
    $message = Message::factory()->create([
        'processed_at' => null,
        'failed_at' => now(),
        'failure_reason' => 'AI timed out',
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post(route('web.messages.retry', $message))
        ->assertRedirect();

    $message->refresh();
    expect($message->failed_at)->toBeNull();
    expect($message->failure_reason)->toBeNull();

    Bus::assertDispatched(ProcessSchoolMessage::class, fn ($job) => $job->message->is($message));
});

test('retry on a web-origin message attributes the run to the retrying admin', function () {
    $message = Message::factory()->create([
        'telegram_chat_id' => null,
        'processed_at' => null,
        'failed_at' => now(),
        'failure_reason' => 'boom',
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->post(route('web.messages.retry', $message))->assertRedirect();

    Bus::assertDispatched(ProcessSchoolMessage::class, fn ($job) => $job->createdBy === $user->id);
});

test('retry on a telegram-origin message keeps channel delivery with no owner', function () {
    $message = Message::factory()->create([
        'telegram_chat_id' => 736610,
        'processed_at' => null,
        'failed_at' => now(),
        'failure_reason' => 'boom',
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->post(route('web.messages.retry', $message))->assertRedirect();

    Bus::assertDispatched(ProcessSchoolMessage::class, fn ($job) => $job->createdBy === null);
});

test('retry is rejected for a message that has not failed', function () {
    $message = Message::factory()->create([
        'processed_at' => now(),
        'failed_at' => null,
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->post(route('web.messages.retry', $message))->assertRedirect();

    Bus::assertNotDispatched(ProcessSchoolMessage::class);
});

test('retry requires authentication', function () {
    $message = Message::factory()->create(['failed_at' => now()]);

    $this->post(route('web.messages.retry', $message))->assertRedirect(route('login'));

    Bus::assertNotDispatched(ProcessSchoolMessage::class);
});
