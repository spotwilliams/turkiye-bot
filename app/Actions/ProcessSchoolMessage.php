<?php

namespace App\Actions;

use App\Ai\Agents\SchoolMessageProcessor;
use App\Data\ProcessedMessageData;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class ProcessSchoolMessage
{
    /**
     * Run the AI agent on the Turkish text and persist the resulting
     * Message, Tasks, and Reminders in a single transaction. Returns the
     * hydrated Message with tasks.reminders loaded.
     */
    public function execute(string $text, int $chatId, int $messageId): Message
    {
        $data = $this->callAgent($text);

        return DB::transaction(function () use ($data, $text, $chatId, $messageId): Message {
            $message = Message::create([
                'telegram_chat_id' => $chatId,
                'telegram_message_id' => $messageId,
                'original_text' => $text,
                'translation_en' => $data->translation_en,
                'translation_es' => $data->translation_es,
                'summary' => $data->summary,
                'raw_processor_response' => $data->toArray(),
                'processed_at' => now(),
            ]);

            foreach ($data->tasks as $taskData) {
                /** @var Task $task */
                $task = $message->tasks()->create([
                    'telegram_chat_id' => $chatId,
                    'description' => $taskData->description,
                    'category' => $taskData->category,
                    'due_date' => $taskData->due_date,
                    'due_time' => $taskData->due_time,
                    'amount' => $taskData->amount,
                    'currency' => $taskData->currency ?? 'TRY',
                ]);

                foreach ($taskData->reminders as $reminderData) {
                    $task->reminders()->create([
                        'scheduled_at' => $reminderData->scheduled_at,
                        'message' => $reminderData->message,
                        'type' => $reminderData->type,
                    ]);
                }
            }

            return $message->load('tasks.reminders');
        });
    }

    private function callAgent(string $text): ProcessedMessageData
    {
        $response = (new SchoolMessageProcessor)->prompt($text);

        return ProcessedMessageData::from([
            'translation_en' => (string) $response['translation_en'],
            'translation_es' => (string) $response['translation_es'],
            'summary' => (string) $response['summary'],
            'tasks' => $response['tasks'] ?? [],
        ]);
    }
}
