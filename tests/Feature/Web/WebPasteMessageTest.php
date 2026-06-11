<?php

use App\Jobs\ProcessSchoolMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('pasting a message creates a processing-state row and dispatches the job with the creator', function () {
    Queue::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post(route('web.messages.store'), ['text' => 'Değerli veliler, Cuma günü 350 TL.'])
        ->assertRedirect();

    $message = Message::first();
    expect($message)->not->toBeNull();
    expect($message->telegram_chat_id)->toBeNull();
    expect($message->processed_at)->toBeNull();
    expect($message->original_text)->toContain('350 TL');

    Queue::assertPushed(
        ProcessSchoolMessage::class,
        fn (ProcessSchoolMessage $job) => $job->message->is($message) && $job->createdBy === $user->id
    );
});

test('pasting requires authentication', function () {
    $this->post(route('web.messages.store'), ['text' => 'hello'])->assertRedirect(route('login'));
});

test('pasting an empty message is rejected', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post(route('web.messages.store'), ['text' => ''])
        ->assertSessionHasErrors('text');

    expect(Message::count())->toBe(0);
});
