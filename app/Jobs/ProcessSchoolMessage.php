<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\Task;
use App\Services\SchoolMessageProcessor;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessSchoolMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $messageText,
        public int $chatId,
        public int $messageId,
    ) {}

    public function handle(SchoolMessageProcessor $processor, TelegramService $telegram): void
    {
        // TODO: if the process fails we should trigger a failover job to process the message with AI later. Also notify the admin of the site
        $result = $processor->process($this->messageText);

        $message = DB::transaction(function () use ($result): Message {
            $message = Message::create([
                'telegram_chat_id' => $this->chatId,
                'telegram_message_id' => $this->messageId,
                'original_text' => $this->messageText,
                'translation_en' => $result['translation_en'],
                'translation_es' => $result['translation_es'],
                'summary' => $result['summary'],
                'raw_processor_response' => $result,
                'processed_at' => now(),
            ]);

            foreach ($result['tasks'] as $taskData) {
                /** @var Task $task */
                $task = $message->tasks()->create([
                    'telegram_chat_id' => $this->chatId,
                    'description' => $taskData['description'],
                    'category' => $taskData['category'],
                    'due_date' => $taskData['due_date'],
                    'due_time' => $taskData['due_time'] ?? null,
                    'amount' => $taskData['amount'] ?? null,
                    'currency' => $taskData['currency'] ?? 'TRY',
                ]);

                foreach ($taskData['reminders'] as $reminderData) {
                    $task->reminders()->create([
                        'scheduled_at' => $reminderData['scheduled_at'],
                        'message' => $reminderData['message'],
                        'type' => $reminderData['type'],
                    ]);
                }
            }

            return $message;
        });

        $taskCount = $message->tasks()->count();
        $telegram->sendProcessedConfirmation($this->chatId, $message, $taskCount);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
