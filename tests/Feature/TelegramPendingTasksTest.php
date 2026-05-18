<?php

use App\Models\FamilyMember;
use App\Models\Message;
use App\Models\Task;
use App\Services\TelegramService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    FamilyMember::factory()->create(['telegram_user_id' => 1]);
    FamilyMember::factory()->create(['telegram_user_id' => 9]);
    FamilyMember::factory()->create(['telegram_user_id' => 42]);
    FamilyMember::factory()->create(['telegram_user_id' => 100]);
});

beforeEach(function () {
    Date::setTestNow(CarbonImmutable::parse('2026-04-24 09:00:00', config('app.timezone')));
});

test('/pending replies with friendly empty state when no tasks', function () {
    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendMessage')
        ->once()
        ->withArgs(fn (int $chatId, string $text) => $chatId === 42 && str_contains($text, 'No pending tasks'))
        ->andReturnTrue();
    $this->app->instance(TelegramService::class, $telegram);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson(route('telegram.webhook'), [
        'message' => ['message_id' => 1, 'chat' => ['id' => 42], 'from' => ['id' => 42], 'text' => '/pending'],
    ])->assertSuccessful()->assertJson(['command' => 'pending']);
});

test('/pending groups tasks by source message and includes due/assignee', function () {
    $msg1 = Message::factory()->create([
        'telegram_chat_id' => 100,
        'summary' => 'Field trip info and red t-shirt request.',
        'original_text' => 'msg1',
        'normalized_text' => 'msg1',
        'normalized_text_hash' => hash('sha256', 'msg1'),
    ]);
    $msg2 = Message::factory()->create([
        'telegram_chat_id' => 100,
        'summary' => 'Math homework assignment.',
        'original_text' => 'msg2',
        'normalized_text' => 'msg2',
        'normalized_text_hash' => hash('sha256', 'msg2'),
    ]);

    Task::factory()->create([
        'id' => 123,
        'message_id' => $msg1->id,
        'telegram_chat_id' => 100,
        'description' => 'Bring 350 TL for field trip',
        'due_date' => '2026-04-26',
        'assigned_to' => 'father',
        'status' => 'pending',
    ]);
    Task::factory()->create([
        'id' => 124,
        'message_id' => $msg1->id,
        'telegram_chat_id' => 100,
        'description' => 'Bring red t-shirt',
        'due_date' => '2026-04-26',
        'assigned_to' => 'both',
        'status' => 'pending',
    ]);
    Task::factory()->create([
        'id' => 125,
        'message_id' => $msg2->id,
        'telegram_chat_id' => 100,
        'description' => 'Math homework pages 45-48',
        'due_date' => '2026-04-20',
        'assigned_to' => 'mother',
        'status' => 'pending',
    ]);

    $captured = null;
    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendMessage')
        ->once()
        ->andReturnUsing(function (int $chatId, string $text) use (&$captured) {
            $captured = $text;

            return true;
        });
    $this->app->instance(TelegramService::class, $telegram);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson(route('telegram.webhook'), [
        'message' => ['message_id' => 1, 'chat' => ['id' => 100], 'from' => ['id' => 100], 'text' => '/pending'],
    ])->assertSuccessful();

    expect($captured)
        ->toContain('Field trip info')
        ->toContain('Math homework assignment')
        ->toContain('(123) Bring 350 TL for field trip')
        ->toContain('(124) Bring red t-shirt')
        ->toContain('(125) Math homework pages 45-48')
        ->toContain('26/04/26')
        ->toContain('remaining')
        ->toContain('delayed by')
        ->toContain('Who: father')
        ->toContain('Who: both')
        ->toContain('Who: mother');
});

test('/pending returns only tasks for the requesting chat', function () {
    $mine = Message::factory()->create([
        'telegram_chat_id' => 1,
        'summary' => 'mine',
        'original_text' => 'mine',
        'normalized_text' => 'mine',
        'normalized_text_hash' => hash('sha256', 'mine'),
    ]);
    $theirs = Message::factory()->create([
        'telegram_chat_id' => 2,
        'summary' => 'theirs',
        'original_text' => 'theirs',
        'normalized_text' => 'theirs',
        'normalized_text_hash' => hash('sha256', 'theirs'),
    ]);

    Task::factory()->create([
        'message_id' => $mine->id,
        'telegram_chat_id' => 1,
        'description' => 'my task',
        'due_date' => '2026-05-01',
        'status' => 'pending',
    ]);
    Task::factory()->create([
        'message_id' => $theirs->id,
        'telegram_chat_id' => 2,
        'description' => 'their task',
        'due_date' => '2026-05-01',
        'status' => 'pending',
    ]);

    $captured = null;
    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendMessage')
        ->once()
        ->andReturnUsing(function (int $chatId, string $text) use (&$captured) {
            $captured = $text;

            return true;
        });
    $this->app->instance(TelegramService::class, $telegram);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson(route('telegram.webhook'), [
        'message' => ['message_id' => 1, 'chat' => ['id' => 1], 'from' => ['id' => 1], 'text' => '/pending'],
    ])->assertSuccessful();

    expect($captured)->toContain('my task')->not->toContain('their task');
});

test('/pending excludes completed tasks', function () {
    $msg = Message::factory()->create([
        'telegram_chat_id' => 9,
        'summary' => 'done summary',
        'original_text' => 'x',
        'normalized_text' => 'x',
        'normalized_text_hash' => hash('sha256', 'x'),
    ]);

    Task::factory()->create([
        'message_id' => $msg->id,
        'telegram_chat_id' => 9,
        'description' => 'already done',
        'due_date' => '2026-05-01',
        'status' => 'completed',
    ]);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendMessage')
        ->once()
        ->withArgs(fn (int $chatId, string $text) => str_contains($text, 'No pending tasks'))
        ->andReturnTrue();
    $this->app->instance(TelegramService::class, $telegram);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson(route('telegram.webhook'), [
        'message' => ['message_id' => 1, 'chat' => ['id' => 9], 'from' => ['id' => 9], 'text' => '/pending'],
    ])->assertSuccessful();
});
