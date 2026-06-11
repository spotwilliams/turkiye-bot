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
     * Run the AI agent on the message's original text and persist
     * translations, summary, tasks, and reminders. Updates the given
     * Message row in place (it was created by the webhook in
     * "processing" state). Returns the hydrated Message.
     */
    public function __construct(
        private GenerateTaskReminders $generateReminders = new GenerateTaskReminders,
    ) {}

    public function execute(Message $message, ?int $createdBy = null): Message
    {
        $data = $this->callAgent($message->original_text);

        return DB::transaction(function () use ($data, $message, $createdBy): Message {
            $message->update([
                'translation_en' => $data->translation_en,
                'translation_es' => $data->translation_es,
                'summary' => $data->summary,
                'raw_processor_response' => $data->toArray(),
                'processed_at' => now(),
            ]);

            foreach ($data->tasks as $taskData) {
                /** @var Task $task */
                $task = $message->tasks()->create([
                    'telegram_chat_id' => $message->telegram_chat_id,
                    'created_by' => $createdBy,
                    'description' => $taskData->description,
                    'category' => $taskData->category,
                    'due_date' => $taskData->due_date,
                    'due_time' => $taskData->due_time,
                    'amount' => $taskData->amount,
                    'currency' => $taskData->currency ?? 'TRY',
                ]);

                foreach ($this->generateReminders->for($task) as $reminder) {
                    $task->reminders()->create($reminder);
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
