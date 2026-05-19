<?php

use App\Actions\BuildPendingTasksReport;
use App\Models\Message;
use App\Models\Task;

test('the pending report prefixes each task with #id for use with /done', function () {
    $message = Message::create([
        'telegram_chat_id' => 1,
        'original_text' => 't',
        'translation_en' => 'e',
        'translation_es' => 's',
        'summary' => 'sum',
        'processed_at' => now(),
    ]);
    $task = Task::create([
        'message_id' => $message->id,
        'telegram_chat_id' => 1,
        'description' => 'Bring 350 TL',
        'category' => 'money',
        'due_date' => now()->addDay()->toDateString(),
        'status' => 'pending',
    ]);

    $report = app(BuildPendingTasksReport::class)->execute(1);

    expect($report)->toContain("#{$task->id}");
});
