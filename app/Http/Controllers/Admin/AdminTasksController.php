<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTasksController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $category = $request->string('category')->toString();

        $tasks = Task::query()
            ->with('message:id,summary')
            ->withCount('reminders')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByRaw('due_time IS NULL')
            ->orderBy('due_time')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Task $task): array => [
                'id' => $task->id,
                'ref' => $this->ref($task->id),
                'description' => $task->description,
                'category' => $task->category,
                'status' => $task->status,
                'due_date' => $task->due_date?->toDateString(),
                'due_time' => $task->due_time,
                'amount' => $task->amount,
                'currency' => $task->currency,
                'assigned_to' => $task->assigned_to,
                'reminders_count' => $task->reminders_count,
                'message_id' => $task->message_id,
                'message_summary' => $task->message?->summary,
            ]);

        return Inertia::render('Admin/Tasks/Index', [
            'tasks' => $tasks,
            'filters' => [
                'status' => $status ?: null,
                'category' => $category ?: null,
            ],
        ]);
    }

    public function show(Task $task): Response
    {
        $task->load([
            'message:id,summary,telegram_chat_id',
            'reminders' => fn ($query) => $query->orderBy('scheduled_at'),
        ]);

        return Inertia::render('Admin/Tasks/Show', [
            'task' => [
                'id' => $task->id,
                'ref' => $this->ref($task->id),
                'description' => $task->description,
                'category' => $task->category,
                'status' => $task->status,
                'due_date' => $task->due_date?->toDateString(),
                'due_time' => $task->due_time,
                'amount' => $task->amount,
                'currency' => $task->currency,
                'assigned_to' => $task->assigned_to,
                'completed_at' => $task->completed_at,
                'created_at' => $task->created_at,
                'telegram_chat_id' => (string) $task->telegram_chat_id,
                'message' => $task->message ? [
                    'id' => $task->message->id,
                    'ref' => 'MSG-'.str_pad((string) $task->message->id, 3, '0', STR_PAD_LEFT),
                    'summary' => $task->message->summary,
                ] : null,
                'reminders' => $task->reminders->map(fn (Reminder $reminder) => [
                    'id' => $reminder->id,
                    'scheduled_at' => $reminder->scheduled_at,
                    'type' => $reminder->type,
                    'sent' => (bool) $reminder->sent,
                    'sent_at' => $reminder->sent_at,
                    'text' => $reminder->message,
                ])->all(),
            ],
        ]);
    }

    private function ref(int $id): string
    {
        return 'TASK-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }
}
