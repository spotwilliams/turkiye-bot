<?php

use App\Models\FamilyMember;
use App\Models\Message;
use App\Models\Task;
use App\Services\TelegramService;
use Mockery\MockInterface;

function postDone(string $text, int $fromId, int $chatId)
{
    return test()->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
        ->postJson(route('telegram.webhook'), [
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => $chatId],
                'from' => ['id' => $fromId],
                'text' => $text,
            ],
        ]);
}

function seedTask(int $chatId, string $status = 'pending'): Task
{
    $message = Message::create([
        'telegram_chat_id' => $chatId,
        'original_text' => 't',
        'translation_en' => 'e',
        'translation_es' => 's',
        'summary' => 'sum',
        'processed_at' => now(),
    ]);

    return Task::create([
        'message_id' => $message->id,
        'telegram_chat_id' => $chatId,
        'description' => 'Bring 350 TL',
        'category' => 'money',
        'due_date' => now()->addDay()->toDateString(),
        'status' => $status,
    ]);
}

test('/done {id} marks a pending task complete and confirms the remaining count', function () {
    FamilyMember::factory()->create(['telegram_user_id' => 555]);
    $task = seedTask(555);

    $reply = null;
    $telegram = $this->mock(TelegramService::class, function (MockInterface $m) use (&$reply) {
        $m->shouldReceive('sendMessage')->once()
            ->andReturnUsing(function (int $chatId, string $text) use (&$reply) {
                $reply = $text;

                return true;
            });
    });
    $this->app->instance(TelegramService::class, $telegram);

    postDone("/done {$task->id}", 555, 555)
        ->assertSuccessful()
        ->assertJson(['ok' => true, 'command' => 'done', 'result' => 'completed']);

    $task->refresh();
    expect($task->status)->toBe('completed');
    expect($task->completed_at)->not->toBeNull();
    expect($reply)->toContain('Bring 350 TL');
    expect($reply)->toContain('0 pending');
});

test('/done on an already-completed task is idempotent', function () {
    FamilyMember::factory()->create(['telegram_user_id' => 555]);
    $task = seedTask(555, 'completed');
    $task->update(['completed_at' => now()->subDay()]);
    $completedAt = $task->fresh()->completed_at;

    $reply = null;
    $telegram = $this->mock(TelegramService::class, function (MockInterface $m) use (&$reply) {
        $m->shouldReceive('sendMessage')->once()
            ->andReturnUsing(function (int $c, string $t) use (&$reply) {
                $reply = $t;

                return true;
            });
    });
    $this->app->instance(TelegramService::class, $telegram);

    postDone("/done {$task->id}", 555, 555)
        ->assertJson(['result' => 'already_done']);

    expect($reply)->toContain('Already done');
    expect($task->fresh()->completed_at->equalTo($completedAt))->toBeTrue();
});

test('/done with an unknown id returns not found', function () {
    FamilyMember::factory()->create(['telegram_user_id' => 555]);

    $reply = null;
    $telegram = $this->mock(TelegramService::class, function (MockInterface $m) use (&$reply) {
        $m->shouldReceive('sendMessage')->once()
            ->andReturnUsing(function (int $c, string $t) use (&$reply) {
                $reply = $t;

                return true;
            });
    });
    $this->app->instance(TelegramService::class, $telegram);

    postDone('/done 99999', 555, 555)
        ->assertJson(['result' => 'not_found']);

    expect($reply)->toContain('not found');
});

test('/done for a task belonging to another chat is treated as not found', function () {
    FamilyMember::factory()->create(['telegram_user_id' => 555]);
    FamilyMember::factory()->create(['telegram_user_id' => 666]);
    $other = seedTask(666);

    $reply = null;
    $telegram = $this->mock(TelegramService::class, function (MockInterface $m) use (&$reply) {
        $m->shouldReceive('sendMessage')->once()
            ->andReturnUsing(function (int $c, string $t) use (&$reply) {
                $reply = $t;

                return true;
            });
    });
    $this->app->instance(TelegramService::class, $telegram);

    postDone("/done {$other->id}", 555, 555)
        ->assertJson(['result' => 'not_found']);

    expect($other->fresh()->status)->toBe('pending');
});

test('/done with non-numeric or missing id returns usage help', function () {
    FamilyMember::factory()->create(['telegram_user_id' => 555]);

    $replies = [];
    $telegram = $this->mock(TelegramService::class, function (MockInterface $m) use (&$replies) {
        $m->shouldReceive('sendMessage')->twice()
            ->andReturnUsing(function (int $c, string $t) use (&$replies) {
                $replies[] = $t;

                return true;
            });
    });
    $this->app->instance(TelegramService::class, $telegram);

    postDone('/done abc', 555, 555)->assertJson(['result' => 'usage']);
    postDone('/done', 555, 555)->assertJson(['result' => 'usage']);

    expect($replies[0])->toContain('Usage');
    expect($replies[1])->toContain('Usage');
});
