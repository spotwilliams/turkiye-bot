<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Reminder;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMessagesController extends Controller
{
    public function index(Request $request): Response
    {
        $chatId = $request->string('chat_id')->toString();
        $processed = $request->string('processed')->toString();

        $messages = Message::query()
            ->withCount('tasks')
            ->when($chatId !== '', fn ($q) => $q->where('telegram_chat_id', $chatId))
            ->when($processed === 'processed', fn ($q) => $q->whereNotNull('processed_at'))
            ->when($processed === 'pending', fn ($q) => $q->whereNull('processed_at'))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Message $message): array => [
                'id' => $message->id,
                'ref' => $this->ref($message->id),
                'telegram_chat_id' => (string) $message->telegram_chat_id,
                'summary' => $message->summary,
                'original_text' => $message->original_text,
                'processed_at' => $message->processed_at,
                'failed_at' => $message->failed_at,
                'failure_reason' => $message->failure_reason,
                'status' => $this->status($message),
                'created_at' => $message->created_at,
                'tasks_count' => $message->tasks_count,
            ]);

        return Inertia::render('Admin/Messages/Index', [
            'messages' => $messages,
            'filters' => [
                'chat_id' => $chatId ?: null,
                'processed' => $processed ?: null,
            ],
        ]);
    }

    public function show(Message $message): Response
    {
        $message->load([
            'tasks' => fn ($query) => $query->orderBy('due_date')->orderByRaw('due_time IS NULL, due_time'),
            'tasks.reminders' => fn ($query) => $query->orderBy('scheduled_at'),
        ]);

        return Inertia::render('Admin/Messages/Show', [
            'message' => [
                'id' => $message->id,
                'ref' => $this->ref($message->id),
                'telegram_chat_id' => (string) $message->telegram_chat_id,
                'original_turkish' => $message->original_text,
                'english' => $message->translation_en,
                'spanish' => $message->translation_es,
                'summary' => $message->summary,
                'processed_at' => $message->processed_at,
                'failed_at' => $message->failed_at,
                'failure_reason' => $message->failure_reason,
                'status' => $this->status($message),
                'created_at' => $message->created_at,
                'tasks' => $this->serializeTasks($message),
            ],
        ]);
    }

    private function ref(int $id): string
    {
        return 'MSG-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }

    private function status(Message $message): string
    {
        if ($message->failed_at !== null) {
            return 'failed';
        }

        return $message->processed_at !== null ? 'processed' : 'pending';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeTasks(Message $message): array
    {
        $out = [];
        /** @var Task $task */
        foreach ($message->tasks as $task) {
            $dueDate = CarbonImmutable::parse($task->due_date)->toDateString();
            /** @var array<int, array<string, mixed>> $reminders */
            $reminders = [];
            /** @var Reminder $reminder */
            foreach ($task->reminders as $reminder) {
                $reminders[] = [
                    'id' => $reminder->id,
                    'scheduled_at' => $reminder->scheduled_at,
                    'type' => $reminder->type,
                    'sent' => (bool) $reminder->sent,
                    'sent_at' => $reminder->sent_at,
                    'text' => $reminder->message,
                ];
            }

            $out[] = [
                'id' => $task->id,
                'description' => $task->description,
                'category' => $task->category,
                'status' => $task->status,
                'due_date' => $dueDate,
                'due_time' => $task->due_time,
                'amount' => $task->amount,
                'currency' => $task->currency,
                'assigned_to' => $task->assigned_to,
                'reminders' => $reminders,
            ];
        }

        return $out;
    }
}
