<?php

use App\Models\Message;
use App\Models\Task;
use App\Models\User;

test('a web-origin task persists with a null telegram_chat_id and a created_by user', function () {
    $user = User::factory()->create();
    $message = Message::factory()->create(['telegram_chat_id' => null]);

    $task = Task::factory()->create([
        'message_id' => $message->id,
        'telegram_chat_id' => null,
        'created_by' => $user->id,
    ]);

    $fresh = $task->fresh();
    expect($fresh->telegram_chat_id)->toBeNull();
    expect($fresh->created_by)->toBe($user->id);
});
