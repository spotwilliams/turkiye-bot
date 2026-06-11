<?php

use App\Actions\ProcessSchoolMessage as ProcessSchoolMessageAction;
use App\Jobs\ProcessSchoolMessage;
use App\Models\Message;
use App\Services\TelegramService;

test('job delegates to action and sends telegram confirmation', function () {
    $message = Message::factory()->create([
        'telegram_chat_id' => 123,
        'telegram_message_id' => 99,
    ]);
    $message->setRelation('tasks', collect());

    $action = Mockery::mock(ProcessSchoolMessageAction::class);
    $action->shouldReceive('execute')
        ->once()
        ->with(Mockery::on(fn (Message $m): bool => $m->is($message)), null)
        ->andReturn($message);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldReceive('sendProcessedConfirmation')
        ->once()
        ->with(123, $message, 0)
        ->andReturnTrue();

    (new ProcessSchoolMessage($message))->handle($action, $telegram);
});

test('web-origin job passes the creator and sends no Telegram confirmation', function () {
    $message = Message::factory()->create(['telegram_chat_id' => null]);
    $message->setRelation('tasks', collect());

    $action = Mockery::mock(ProcessSchoolMessageAction::class);
    $action->shouldReceive('execute')
        ->once()
        ->with(Mockery::on(fn (Message $m): bool => $m->is($message)), 42)
        ->andReturn($message);

    $telegram = Mockery::mock(TelegramService::class);
    $telegram->shouldNotReceive('sendProcessedConfirmation');

    (new ProcessSchoolMessage($message, 42))->handle($action, $telegram);
});
