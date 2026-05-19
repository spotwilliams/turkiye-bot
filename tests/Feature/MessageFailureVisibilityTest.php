<?php

use App\Jobs\ProcessSchoolMessage;
use App\Models\FamilyMember;
use App\Models\Message;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Queue;

test('the webhook creates a message row in processing state before dispatching the job', function () {
    Queue::fake();

    FamilyMember::factory()->create(['telegram_user_id' => 1234]);

    $payload = [
        'message' => [
            'message_id' => 99,
            'chat' => ['id' => 1234],
            'from' => ['id' => 1234],
            'text' => 'Yarin okula 2 A4 kagidi getirin.',
        ],
    ];

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
        ->postJson(route('telegram.webhook'), $payload)
        ->assertSuccessful();

    $message = Message::first();
    expect($message)->not->toBeNull();
    expect($message->original_text)->toBe('Yarin okula 2 A4 kagidi getirin.');
    expect($message->processed_at)->toBeNull();
    expect($message->failed_at)->toBeNull();

    Queue::assertPushed(ProcessSchoolMessage::class, function (ProcessSchoolMessage $job) use ($message) {
        return $job->message->is($message);
    });
});

test('when the job exhausts retries the message row is marked failed and the user gets an apology', function () {
    $message = Message::factory()->create([
        'telegram_chat_id' => 4242,
        'original_text' => 'broken text',
        'processed_at' => null,
        'failed_at' => null,
    ]);

    $reply = null;
    $telegram = $this->mock(TelegramService::class, function ($m) use (&$reply) {
        $m->shouldReceive('sendMessage')->once()
            ->andReturnUsing(function (int $chatId, string $text) use (&$reply) {
                expect($chatId)->toBe(4242);
                $reply = $text;

                return true;
            });
    });
    $this->app->instance(TelegramService::class, $telegram);

    $job = new ProcessSchoolMessage($message);
    $job->failed(new RuntimeException('AI exploded'));

    $message->refresh();
    expect($message->failed_at)->not->toBeNull();
    expect($message->failure_reason)->toContain('AI exploded');
    expect($reply)->toContain("Couldn't process");
});

test('admin messages index exposes a status of failed when the message has failed_at set', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Message::factory()->create([
        'failed_at' => now(),
        'failure_reason' => 'boom',
        'processed_at' => null,
    ]);
    Message::factory()->create(['processed_at' => now()]);
    Message::factory()->create(['processed_at' => null]);

    $this->actingAs($user)
        ->get(route('admin.messages.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Messages/Index')
            ->has('messages.data', 3)
            ->where('messages.data', fn ($rows) => collect($rows)->pluck('status')->sort()->values()->all() === ['failed', 'pending', 'processed'])
        );
});

test('admin messages show exposes failure reason and failed_at when the message failed', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $failed = Message::factory()->create([
        'failed_at' => now(),
        'failure_reason' => 'AI exploded',
        'processed_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('admin.messages.show', $failed))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Messages/Show')
            ->where('message.status', 'failed')
            ->where('message.failure_reason', 'AI exploded')
            ->has('message.failed_at')
        );
});
