<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminRemindersController extends Controller
{
    public function index(Request $request): Response
    {
        $sent = $request->string('sent')->toString();
        $type = $request->string('type')->toString();
        $window = $request->string('window')->toString();

        $reminders = Reminder::query()
            ->with('task:id,description,category,status,message_id,telegram_chat_id')
            ->when($sent === 'sent', fn ($q) => $q->where('sent', true))
            ->when($sent === 'pending', fn ($q) => $q->where('sent', false))
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($window === 'due', fn ($q) => $q->where('sent', false)->where('scheduled_at', '<=', now()))
            ->when($window === 'upcoming', fn ($q) => $q->where('sent', false)->where('scheduled_at', '>', now()))
            ->orderBy('scheduled_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Reminder $reminder): array => [
                'id' => $reminder->id,
                'ref' => $this->ref($reminder->id),
                'scheduled_at' => $reminder->scheduled_at,
                'type' => $reminder->type,
                'sent' => (bool) $reminder->sent,
                'sent_at' => $reminder->sent_at,
                'text' => $reminder->message,
                'task' => $reminder->task ? [
                    'id' => $reminder->task->id,
                    'ref' => 'TASK-'.str_pad((string) $reminder->task->id, 3, '0', STR_PAD_LEFT),
                    'description' => $reminder->task->description,
                    'category' => $reminder->task->category,
                    'status' => $reminder->task->status,
                ] : null,
            ]);

        return Inertia::render('Admin/Reminders/Index', [
            'reminders' => $reminders,
            'filters' => [
                'sent' => $sent ?: null,
                'type' => $type ?: null,
                'window' => $window ?: null,
            ],
        ]);
    }

    public function show(Reminder $reminder): Response
    {
        $reminder->load('task.message:id,summary');

        return Inertia::render('Admin/Reminders/Show', [
            'reminder' => [
                'id' => $reminder->id,
                'ref' => $this->ref($reminder->id),
                'scheduled_at' => $reminder->scheduled_at,
                'type' => $reminder->type,
                'sent' => (bool) $reminder->sent,
                'sent_at' => $reminder->sent_at,
                'text' => $reminder->message,
                'created_at' => $reminder->created_at,
                'task' => $reminder->task ? [
                    'id' => $reminder->task->id,
                    'ref' => 'TASK-'.str_pad((string) $reminder->task->id, 3, '0', STR_PAD_LEFT),
                    'description' => $reminder->task->description,
                    'category' => $reminder->task->category,
                    'status' => $reminder->task->status,
                    'due_date' => $reminder->task->due_date?->toDateString(),
                    'message' => $reminder->task->message ? [
                        'id' => $reminder->task->message->id,
                        'ref' => 'MSG-'.str_pad((string) $reminder->task->message->id, 3, '0', STR_PAD_LEFT),
                        'summary' => $reminder->task->message->summary,
                    ] : null,
                ] : null,
            ],
        ]);
    }

    private function ref(int $id): string
    {
        return 'REM-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }
}
