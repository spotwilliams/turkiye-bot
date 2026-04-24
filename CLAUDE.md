# School Task Reminder Bot — CLAUDE.md

## What This Project Does

Parents receive school messages in Turkish. They paste the message into a Telegram bot.
The system translates, summarizes, extracts tasks, and sends daily reminders so nothing gets forgotten.

---

## Agent Commit Safety Rule

Never, ever under any circumstances should the agent commit without an explicit human request.

---

## User Flow

```
┌──────────────────────────────────────────────────────────────────────┐
│                          INCOMING FLOW                               │
│                                                                      │
│  Parent receives school message (WhatsApp, paper, verbal)            │
│       │                                                              │
│       ▼                                                              │
│  Parent copies/pastes text into Telegram bot                         │
│       │                                                              │
│       ▼                                                              │
│  Telegram sends webhook to Laravel API                               │
│       │                                                              │
│       ▼                                                              │
│  Laravel dispatches ProcessSchoolMessage job to queue                 │
│       │                                                              │
│       ▼                                                              │
│  Job prompts SchoolMessageProcessor agent (Laravel AI SDK)           │
│       │                                                              │
│       ├──▶ 1. Translate (Turkish → English + Spanish)                │
│       ├──▶ 2. Summarize (what is the school saying?)                 │
│       └──▶ 3. Extract tasks (what do parents need to DO?)            │
│                │                                                     │
│                ▼                                                     │
│  Store: original message, translations, summary, tasks, reminders   │
│       │                                                              │
│       ▼                                                              │
│  Bot replies with confirmation:                                      │
│    "Here's what the school said: [summary]"                          │
│    "Tasks created: [list]"                                           │
│    "I'll remind you when needed."                                    │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│                        REMINDER FLOW                                 │
│                                                                      │
│  Every day at 8:00 AM (Europe/Istanbul)                              │
│       │                                                              │
│       ▼                                                              │
│  Laravel scheduler runs digest:send command                          │
│       │                                                              │
│       ├──▶ TODAY: tasks due today (urgent)                           │
│       ├──▶ TOMORROW: tasks due tomorrow (prepare)                    │
│       └──▶ THIS WEEK: remaining tasks for the week                  │
│                │                                                     │
│                ▼                                                     │
│  Bot sends formatted digest to Telegram                              │
│                                                                      │
│                                                                      │
│  Throughout the day (every 30 min)                                   │
│       │                                                              │
│       ▼                                                              │
│  Laravel checks for task-specific reminders                          │
│       │                                                              │
│       ▼                                                              │
│  Bot sends individual reminders with [Done] [Snooze] buttons        │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Example Interaction

### Step 1: Parent pastes a message

```
Parent sends to bot:
"Değerli veliler, Cuma günü yapılacak gezi için 350 TL ve
kırmızı tişört getirilmesi gerekmektedir. Ayrıca hafta sonu
matematik ödevi sayfa 45-48 yapılacaktır."
```

### Step 2: Bot responds immediately

```
📨 Got it! Here's what the school said:

🇬🇧 English:
Dear parents, for Friday's field trip, please bring 350 TL and
a red t-shirt. Also, weekend homework is math pages 45-48.

🇪🇸 Spanish:
Estimados padres, para la excursión del viernes, traigan 350 TL
y una camiseta roja. Además, la tarea del fin de semana es
matemáticas páginas 45-48.

📋 Tasks created:

  💰 Bring 350 TL for field trip
     📅 Due: Friday, Jan 17
     ⏰ Reminders: Thu 8pm + Fri 7:30am

  🎒 Bring red t-shirt
     📅 Due: Friday, Jan 17
     ⏰ Reminders: Thu 8pm + Fri 7:30am

  📚 Math homework pages 45-48
     📅 Due: Monday, Jan 20
     ⏰ Reminders: Sat 10am + Sun 6pm

I'll remind you when it's time! ✅
```

### Step 3: Daily digest at 8 AM

```
☀️ Good morning! Here's your school day briefing:

📌 TODAY (Friday, Jan 17):
  💰 Bring 350 TL for field trip — put in backpack!
  🎒 Bring red t-shirt — pack it!

📅 THIS WEEKEND:
  📚 Math homework pages 45-48 (due Monday)

🗓️ LATER THIS WEEK:
  Nothing else — you're all set!

Reply /done 1 to mark a task complete.
```

### Step 4: Marking tasks done

```
Parent: /done 1
Bot: ✅ "Bring 350 TL for field trip" marked as done!
     2 tasks remaining.
```

---

## Tech Stack

| Component        | Technology                                         |
|------------------|----------------------------------------------------|
| Framework        | Laravel 13 (released March 17, 2026)               |
| PHP              | 8.3+                                               |
| Local Dev        | Laravel Sail (Docker)                               |
| Frontend         | Vue 3 + Inertia.js (for web dashboard)              |
| AI Integration   | Laravel AI SDK (`laravel/ai`) — first-party         |
| LLM Provider     | Anthropic Claude (via AI SDK, provider-agnostic)    |
| Database         | PostgreSQL                                          |
| Queue            | Redis + Laravel queue worker                        |
| Scheduler        | Laravel scheduler (cron)                            |
| Bot Interface    | Telegram Bot API                                    |
| CSS              | Tailwind CSS                                        |

---

## Project Setup

```bash
# Create the project
laravel new school-reminder

# During setup, select:
#   - Starter Kit: None (or Vue with Inertia if you want dashboard from day 1)
#   - Database: PostgreSQL
#   - Sail services: pgsql, redis

# Enter the project
cd school-reminder

# Install the Laravel AI SDK
composer require laravel/ai

# Publish AI SDK config and migrations
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"

# Run migrations (creates agent_conversations + agent_conversation_messages tables too)
./vendor/bin/sail artisan migrate

# Create the agent
./vendor/bin/sail artisan make:agent SchoolMessageProcessor --structured
```

---

## Environment Variables

```env
APP_TIMEZONE=Europe/Istanbul

# Laravel Sail
SAIL_XDEBUG_MODE=off

# Database (Sail defaults)
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=school_reminder
DB_USERNAME=sail
DB_PASSWORD=password

# Redis (Sail defaults)
REDIS_HOST=redis
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Telegram Bot
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/api/telegram/webhook

# AI SDK — Anthropic as primary provider
ANTHROPIC_API_KEY=

# Reminder Settings
DAILY_DIGEST_HOUR=08:00
REMINDER_CHECK_INTERVAL=30
```

---

## AI SDK Configuration

### config/ai.php (relevant sections)

```php
return [
    'defaults' => [
        'text' => [
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-20250514',
        ],
    ],

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
        ],
        // Optionally add OpenAI as failover:
        // 'openai' => [
        //     'driver' => 'openai',
        //     'key' => env('OPENAI_API_KEY'),
        // ],
    ],
];
```

---

## Database Schema

### migrations/create_messages_table.php

```php
Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->bigInteger('telegram_chat_id');
    $table->bigInteger('telegram_message_id')->nullable();
    $table->text('original_text');
    $table->text('translation_en');
    $table->text('translation_es');
    $table->text('summary');
    $table->json('raw_llm_response')->nullable();
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();

    $table->index('telegram_chat_id');
});
```

### migrations/create_tasks_table.php

```php
Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('message_id')->constrained()->onDelete('cascade');
    $table->bigInteger('telegram_chat_id');
    $table->string('description');
    $table->string('category');             // money, homework, item, event, other
    $table->date('due_date');
    $table->time('due_time')->nullable();
    $table->decimal('amount', 10, 2)->nullable();
    $table->string('currency', 3)->default('TRY');
    $table->string('assigned_to')->default('both');
    $table->string('status')->default('pending');
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->index(['telegram_chat_id', 'status', 'due_date']);
    $table->index(['status', 'due_date']);
});
```

### migrations/create_reminders_table.php

```php
Schema::create('reminders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('task_id')->constrained()->onDelete('cascade');
    $table->dateTime('scheduled_at');
    $table->text('message');
    $table->string('type')->default('action');
    $table->boolean('sent')->default(false);
    $table->timestamp('sent_at')->nullable();
    $table->timestamps();

    $table->index(['sent', 'scheduled_at']);
});
```

### migrations/create_family_members_table.php

```php
Schema::create('family_members', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('role');                 // father, mother
    $table->bigInteger('telegram_user_id')->unique();
    $table->bigInteger('telegram_chat_id');
    $table->string('timezone')->default('Europe/Istanbul');
    $table->json('preferences')->nullable();
    $table->timestamps();
});
```

---

## Directory Structure

```
app/
├── Ai/
│   └── Agents/
│       └── SchoolMessageProcessor.php    ← Laravel AI SDK Agent
├── Console/
│   └── Commands/
│       ├── SendDailyDigest.php
│       ├── ProcessDueReminders.php
│       └── SetTelegramWebhook.php
├── Enums/
│   ├── TaskCategory.php
│   ├── TaskStatus.php
│   └── ReminderType.php
├── Http/
│   └── Controllers/
│       ├── TelegramWebhookController.php
│       └── DashboardController.php       ← Vue 3 / Inertia
├── Jobs/
│   └── ProcessSchoolMessage.php
├── Models/
│   ├── Message.php
│   ├── Task.php
│   ├── Reminder.php
│   └── FamilyMember.php
└── Services/
    └── TelegramService.php

resources/
├── js/
│   ├── app.js
│   ├── Pages/
│   │   ├── Dashboard.vue
│   │   ├── Tasks/
│   │   │   ├── Index.vue
│   │   │   └── Show.vue
│   │   └── Messages/
│   │       └── Index.vue
│   └── Components/
│       ├── TaskCard.vue
│       ├── DigestPreview.vue
│       └── MessageTimeline.vue
└── views/
    └── app.blade.php                     ← Inertia root template
```

---

## Core: SchoolMessageProcessor Agent (Laravel AI SDK)

This is the heart of the app. A dedicated Agent class using Laravel's first-party AI SDK
with structured output so responses are always predictable and parseable.

### app/Ai/Agents/SchoolMessageProcessor.php

```php
<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class SchoolMessageProcessor implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public string $currentDate = '',
        public string $dayOfWeek = '',
    ) {
        $this->currentDate = $this->currentDate ?: now()->format('Y-m-d');
        $this->dayOfWeek = $this->dayOfWeek ?: now()->format('l');
    }

    /**
     * System instructions for the agent.
     */
    public function instructions(): Stringable|string
    {
        return <<<INSTRUCTIONS
        You are a school message processing assistant for parents living in Turkey.

        You receive messages from school teachers written in Turkish.

        For every message, you must:
        1. TRANSLATE the full message into English and Spanish.
        2. SUMMARIZE what the school is communicating in 1-2 sentences (in English).
        3. EXTRACT every actionable task that requires parents to do something.

        For each task, determine:
        - A clear, short description (in English)
        - A category: money, homework, item, event, or other
        - The exact due date (calculate from context + today's date: {$this->currentDate}, {$this->dayOfWeek})
        - A due time if specified, otherwise null
        - A monetary amount if applicable, otherwise null

        For each task, generate smart reminders following these rules:
        - MONEY tasks: evening before at 20:00 ("Prepare X TL") + morning of at 07:30 ("Put X TL in backpack")
        - ITEM tasks: evening before at 20:00 ("Find and prepare [item]") + morning of at 07:30 ("Pack [item] in backpack")
        - HOMEWORK tasks: Saturday at 10:00 ("Start homework: [description]") + Sunday at 18:00 ("Check homework is done")
        - EVENT tasks: 2 days before at 20:00 + evening before at 20:00 + morning of at 07:30

        If the message is just informational with no action required, return an empty tasks array.
        Reminder messages should be short, actionable, and written as if reminding a busy parent.
        INSTRUCTIONS;
    }

    /**
     * Structured output schema — ensures predictable, parseable responses.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'translation_en' => $schema->string()
                ->description('Full English translation of the message')
                ->required(),

            'translation_es' => $schema->string()
                ->description('Full Spanish translation of the message')
                ->required(),

            'summary' => $schema->string()
                ->description('1-2 sentence summary in English')
                ->required(),

            'tasks' => $schema->array()
                ->items(
                    $schema->object(fn (JsonSchema $s) => [
                        'description' => $s->string()
                            ->description('Short task description in English')
                            ->required(),

                        'category' => $s->string()
                            ->enum(['money', 'homework', 'item', 'event', 'other'])
                            ->required(),

                        'due_date' => $s->string()
                            ->description('YYYY-MM-DD format')
                            ->required(),

                        'due_time' => $s->string()
                            ->description('HH:MM format or null')
                            ->nullable(),

                        'amount' => $s->number()
                            ->description('Monetary amount or null')
                            ->nullable(),

                        'currency' => $s->string()
                            ->description('Currency code, defaults to TRY')
                            ->nullable(),

                        'reminders' => $s->array()
                            ->items(
                                $s->object(fn (JsonSchema $r) => [
                                    'scheduled_at' => $r->string()
                                        ->description('YYYY-MM-DDTHH:MM format')
                                        ->required(),
                                    'message' => $r->string()
                                        ->description('Short actionable reminder text')
                                        ->required(),
                                    'type' => $r->string()
                                        ->enum(['preparation', 'action', 'final'])
                                        ->required(),
                                ])
                            )
                            ->required(),
                    ])
                )
                ->required(),
        ];
    }
}
```

### Usage

```php
use App\Ai\Agents\SchoolMessageProcessor;

// Prompt the agent — structured output is guaranteed by the schema
$response = SchoolMessageProcessor::make()->prompt($turkishMessage);

// Access structured data like an array
$response['translation_en'];  // string
$response['translation_es'];  // string
$response['summary'];         // string
$response['tasks'];           // array of task objects
$response['tasks'][0]['description'];  // "Bring 350 TL for field trip"
$response['tasks'][0]['category'];     // "money"
$response['tasks'][0]['reminders'];    // array of reminder objects
```

---

## Jobs

### app/Jobs/ProcessSchoolMessage.php

```php
<?php

namespace App\Jobs;

use App\Ai\Agents\SchoolMessageProcessor;
use App\Models\Message;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSchoolMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private string $turkishText,
        private int $chatId,
        private int $messageId,
    ) {}

    public function handle(TelegramService $telegram): void
    {
        try {
            $response = SchoolMessageProcessor::make()->prompt($this->turkishText);

            DB::transaction(function () use ($response, $telegram) {
                $message = Message::create([
                    'telegram_chat_id'    => $this->chatId,
                    'telegram_message_id' => $this->messageId,
                    'original_text'       => $this->turkishText,
                    'translation_en'      => $response['translation_en'],
                    'translation_es'      => $response['translation_es'],
                    'summary'             => $response['summary'],
                    'raw_llm_response'    => $response->toArray(),
                    'processed_at'        => now(),
                ]);

                $tasks = [];

                foreach ($response['tasks'] as $taskData) {
                    $task = $message->tasks()->create([
                        'telegram_chat_id' => $this->chatId,
                        'description'      => $taskData['description'],
                        'category'         => $taskData['category'],
                        'due_date'         => $taskData['due_date'],
                        'due_time'         => $taskData['due_time'] ?? null,
                        'amount'           => $taskData['amount'] ?? null,
                        'currency'         => $taskData['currency'] ?? 'TRY',
                    ]);

                    foreach ($taskData['reminders'] as $rem) {
                        $task->reminders()->create([
                            'scheduled_at' => $rem['scheduled_at'],
                            'message'      => $rem['message'],
                            'type'         => $rem['type'],
                        ]);
                    }

                    $tasks[] = $task;
                }

                $telegram->sendProcessedConfirmation($this->chatId, $message, $tasks);
            });
        } catch (\Exception $e) {
            Log::error('Failed to process school message', [
                'error'   => $e->getMessage(),
                'chat_id' => $this->chatId,
            ]);

            $telegram->sendMessage(
                $this->chatId,
                "❌ Sorry, I couldn't process that message. Please try again."
            );

            throw $e;
        }
    }
}
```

---

## Enums

### app/Enums/TaskCategory.php

```php
<?php

namespace App\Enums;

enum TaskCategory: string
{
    case Money = 'money';
    case Homework = 'homework';
    case Item = 'item';
    case Event = 'event';
    case Other = 'other';

    public function emoji(): string
    {
        return match ($this) {
            self::Money => '💰',
            self::Homework => '📚',
            self::Item => '🎒',
            self::Event => '📅',
            self::Other => '📌',
        };
    }
}
```

### app/Enums/TaskStatus.php

```php
<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
```

### app/Enums/ReminderType.php

```php
<?php

namespace App\Enums;

enum ReminderType: string
{
    case Preparation = 'preparation';
    case Action = 'action';
    case Final = 'final';
}
```

---

## Services

### app/Services/TelegramService.php

```php
<?php

namespace App\Services;

use App\Enums\TaskCategory;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;
    private string $baseUrl;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    public function sendMessage(int $chatId, string $text, array $options = []): bool
    {
        $response = Http::post("{$this->baseUrl}/sendMessage", array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ], $options));

        if ($response->failed()) {
            Log::error('Telegram send failed', ['chat_id' => $chatId, 'response' => $response->body()]);
            return false;
        }

        return true;
    }

    public function sendProcessedConfirmation(int $chatId, Message $message, array $tasks): void
    {
        $msg = "📨 <b>Got it! Here's what the school said:</b>\n\n";
        $msg .= "🇬🇧 <b>English:</b>\n{$message->translation_en}\n\n";
        $msg .= "🇪🇸 <b>Spanish:</b>\n{$message->translation_es}\n\n";

        if (count($tasks) > 0) {
            $msg .= "📋 <b>Tasks created:</b>\n\n";
            foreach ($tasks as $task) {
                $cat = TaskCategory::from($task->category);
                $msg .= "  {$cat->emoji()} {$task->description}\n";
                $msg .= "     📅 Due: {$task->due_date->format('l, M j')}\n";
                if ($task->amount) {
                    $msg .= "     💵 Amount: {$task->amount} {$task->currency}\n";
                }
                $msg .= "\n";
            }
            $msg .= "⏰ Reminders scheduled. I'll remind you when it's time! ✅";
        } else {
            $msg .= "ℹ️ No actionable tasks — this was informational only.";
        }

        $this->sendMessage($chatId, $msg);
    }

    public function sendReminder(int $chatId, string $message, int $taskId): void
    {
        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '✅ Done', 'callback_data' => "complete_{$taskId}"],
                ['text' => '⏰ Snooze 1h', 'callback_data' => "snooze_{$taskId}_60"],
            ]],
        ];

        $this->sendMessage($chatId, "🔔 <b>Reminder</b>\n\n{$message}", [
            'reply_markup' => json_encode($keyboard),
        ]);
    }

    public function setWebhook(string $url): bool
    {
        return Http::post("{$this->baseUrl}/setWebhook", ['url' => $url])->successful();
    }
}
```

---

## Controllers

### app/Http/Controllers/TelegramWebhookController.php

```php
<?php

namespace App\Http\Controllers;

use App\Enums\TaskCategory;
use App\Jobs\ProcessSchoolMessage;
use App\Models\Reminder;
use App\Models\Task;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(private TelegramService $telegram) {}

    public function handle(Request $request): JsonResponse
    {
        $update = $request->all();
        Log::info('Telegram webhook', $update);

        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        } elseif (isset($update['message']['text'])) {
            $this->handleMessage($update['message']);
        }

        return response()->json(['ok' => true]);
    }

    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'];
        $messageId = $message['message_id'];

        if (str_starts_with($text, '/')) {
            $this->handleCommand($chatId, $text);
            return;
        }

        ProcessSchoolMessage::dispatch($text, $chatId, $messageId);
        $this->telegram->sendMessage($chatId, "📨 Message received! Processing...");
    }

    private function handleCommand(int $chatId, string $command): void
    {
        $parts = explode(' ', $command);
        $cmd = strtolower($parts[0]);

        match ($cmd) {
            '/start' => $this->telegram->sendMessage($chatId,
                "👋 <b>Welcome to School Task Reminder!</b>\n\n" .
                "Paste school messages here and I'll:\n" .
                "• Translate from Turkish\n" .
                "• Extract tasks and deadlines\n" .
                "• Send smart reminders\n\n" .
                "<b>Commands:</b>\n" .
                "/tasks — Pending tasks\n" .
                "/today — Today's tasks\n" .
                "/week — This week\n" .
                "/done {n} — Mark complete\n" .
                "/help — Help"
            ),
            '/tasks' => $this->showTasks($chatId),
            '/today' => $this->showTasks($chatId, 'today'),
            '/week'  => $this->showTasks($chatId, 'week'),
            '/done'  => $this->markDone($chatId, $parts),
            '/help'  => $this->telegram->sendMessage($chatId,
                "📖 Paste a school message → I translate, extract tasks, send reminders.\n" .
                "Every day at 8 AM you get a daily briefing."
            ),
            default => $this->telegram->sendMessage($chatId, "Unknown command. Try /help"),
        };
    }

    private function showTasks(int $chatId, string $filter = 'all'): void
    {
        $query = Task::where('telegram_chat_id', $chatId)
            ->where('status', 'pending')
            ->where('due_date', '>=', now()->toDateString())
            ->orderBy('due_date');

        $query = match ($filter) {
            'today' => $query->whereDate('due_date', now()),
            'week'  => $query->whereBetween('due_date', [now()->toDateString(), now()->endOfWeek()->toDateString()]),
            default => $query,
        };

        $tasks = $query->get();

        if ($tasks->isEmpty()) {
            $this->telegram->sendMessage($chatId, "🎉 No pending tasks!");
            return;
        }

        $msg = "📋 <b>Pending Tasks:</b>\n\n";
        foreach ($tasks as $i => $task) {
            $cat = TaskCategory::from($task->category);
            $n = $i + 1;
            $msg .= "  <b>{$n}.</b> {$cat->emoji()} {$task->description}\n";
            $msg .= "     📅 {$task->due_date->format('l, M j')}\n\n";
        }
        $msg .= "<i>/done {n} to mark complete.</i>";

        $this->telegram->sendMessage($chatId, $msg);
    }

    private function markDone(int $chatId, array $parts): void
    {
        $index = isset($parts[1]) ? (int) $parts[1] : null;
        if (!$index) {
            $this->telegram->sendMessage($chatId, "Usage: /done 1");
            return;
        }

        $tasks = Task::where('telegram_chat_id', $chatId)
            ->where('status', 'pending')
            ->where('due_date', '>=', now()->toDateString())
            ->orderBy('due_date')
            ->get();

        $task = $tasks->get($index - 1);
        if (!$task) {
            $this->telegram->sendMessage($chatId, "❌ Task #{$index} not found.");
            return;
        }

        $task->update(['status' => 'completed', 'completed_at' => now()]);
        $remaining = Task::where('telegram_chat_id', $chatId)->where('status', 'pending')->count();

        $this->telegram->sendMessage($chatId,
            "✅ <b>\"{$task->description}\"</b> done!\n{$remaining} tasks remaining."
        );
    }

    private function handleCallback(array $callback): void
    {
        $data = $callback['data'];
        $chatId = $callback['message']['chat']['id'];

        if (str_starts_with($data, 'complete_')) {
            $task = Task::find((int) str_replace('complete_', '', $data));
            if ($task) {
                $task->update(['status' => 'completed', 'completed_at' => now()]);
                $this->telegram->sendMessage($chatId, "✅ \"{$task->description}\" done!");
            }
        } elseif (str_starts_with($data, 'snooze_')) {
            preg_match('/snooze_(\d+)_(\d+)/', $data, $m);
            $task = Task::find((int) $m[1]);
            if ($task) {
                Reminder::create([
                    'task_id' => $task->id,
                    'scheduled_at' => now()->addMinutes((int) $m[2]),
                    'message' => "⏰ Snoozed: {$task->description}",
                    'type' => 'action',
                ]);
                $this->telegram->sendMessage($chatId, "⏰ Snoozed for {$m[2]} min.");
            }
        }
    }
}
```

---

## Console Commands

### app/Console/Commands/SendDailyDigest.php

```php
<?php

namespace App\Console\Commands;

use App\Enums\TaskCategory;
use App\Models\FamilyMember;
use App\Models\Task;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyDigest extends Command
{
    protected $signature = 'digest:send';
    protected $description = 'Send the 8 AM daily briefing to all families';

    public function handle(TelegramService $telegram): int
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $endOfWeek = now()->endOfWeek()->toDateString();

        $chatIds = FamilyMember::distinct()->pluck('telegram_chat_id');

        foreach ($chatIds as $chatId) {
            $todayTasks = Task::where('telegram_chat_id', $chatId)
                ->where('status', 'pending')->whereDate('due_date', $today)
                ->orderBy('due_time')->get();

            $tomorrowTasks = Task::where('telegram_chat_id', $chatId)
                ->where('status', 'pending')->whereDate('due_date', $tomorrow)->get();

            $weekTasks = Task::where('telegram_chat_id', $chatId)
                ->where('status', 'pending')
                ->whereBetween('due_date', [$tomorrow, $endOfWeek])
                ->whereDate('due_date', '!=', $tomorrow)
                ->orderBy('due_date')->get();

            if ($todayTasks->isEmpty() && $tomorrowTasks->isEmpty() && $weekTasks->isEmpty()) {
                $telegram->sendMessage($chatId, "☀️ Good morning! No school tasks pending. Enjoy your day! 🎉");
                continue;
            }

            $msg = "☀️ <b>Good morning! School briefing:</b>\n\n";

            if ($todayTasks->isNotEmpty()) {
                $msg .= "📌 <b>TODAY (urgent!):</b>\n";
                foreach ($todayTasks as $t) {
                    $msg .= "  " . TaskCategory::from($t->category)->emoji() . " {$t->description}\n";
                }
                $msg .= "\n";
            }

            if ($tomorrowTasks->isNotEmpty()) {
                $msg .= "📅 <b>TOMORROW (prepare tonight):</b>\n";
                foreach ($tomorrowTasks as $t) {
                    $msg .= "  " . TaskCategory::from($t->category)->emoji() . " {$t->description}\n";
                }
                $msg .= "\n";
            }

            if ($weekTasks->isNotEmpty()) {
                $msg .= "🗓️ <b>LATER THIS WEEK:</b>\n";
                foreach ($weekTasks as $t) {
                    $due = Carbon::parse($t->due_date)->format('l');
                    $msg .= "  " . TaskCategory::from($t->category)->emoji() . " {$t->description} ({$due})\n";
                }
                $msg .= "\n";
            }

            $pending = Task::where('telegram_chat_id', $chatId)->where('status', 'pending')->count();
            $msg .= "—\n<i>{$pending} total pending. /tasks to see all.</i>";

            $telegram->sendMessage($chatId, $msg);
        }

        $this->info('Daily digest sent.');
        return self::SUCCESS;
    }
}
```

### app/Console/Commands/ProcessDueReminders.php

```php
<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ProcessDueReminders extends Command
{
    protected $signature = 'reminders:process';
    protected $description = 'Send all due reminders via Telegram';

    public function handle(TelegramService $telegram): int
    {
        $due = Reminder::with('task')
            ->where('sent', false)
            ->where('scheduled_at', '<=', now())
            ->get();

        $this->info("Found {$due->count()} due reminders.");

        foreach ($due as $reminder) {
            if ($reminder->task->status === 'completed') {
                $reminder->update(['sent' => true, 'sent_at' => now()]);
                continue;
            }

            $telegram->sendReminder(
                $reminder->task->telegram_chat_id,
                $reminder->message,
                $reminder->task_id,
            );

            $reminder->update(['sent' => true, 'sent_at' => now()]);
            $this->info("Sent: {$reminder->message}");
        }

        return self::SUCCESS;
    }
}
```

---

## Scheduler

### routes/console.php

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('digest:send')
    ->dailyAt('08:00')
    ->timezone('Europe/Istanbul');

Schedule::command('reminders:process')
    ->everyThirtyMinutes();
```

---

## Routes

### routes/api.php

```php
use App\Http\Controllers\TelegramWebhookController;

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);
```

### routes/web.php (Vue 3 Dashboard)

```php
use App\Http\Controllers\DashboardController;

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/tasks', [DashboardController::class, 'tasks'])->name('tasks.index');
    Route::patch('/tasks/{task}/complete', [DashboardController::class, 'complete'])->name('tasks.complete');
    Route::get('/messages', [DashboardController::class, 'messages'])->name('messages.index');
});
```

---

## Vue 3 Dashboard (resources/js/Pages/Dashboard.vue)

```vue
<script setup>
import { Head } from '@inertiajs/vue3'

defineProps({
    todayTasks: Array,
    upcomingTasks: Array,
    stats: Object,
})
</script>

<template>
    <Head title="School Tasks" />

    <div class="max-w-2xl mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">📋 School Tasks</h1>

        <section class="mb-8">
            <h2 class="text-lg font-semibold mb-3">📌 Today</h2>
            <p v-if="todayTasks.length === 0" class="text-gray-500">Nothing due today 🎉</p>
            <div v-for="task in todayTasks" :key="task.id"
                 class="flex items-center justify-between p-3 mb-2 bg-white rounded-lg shadow-sm border">
                <div>
                    <span class="mr-2">{{ task.category_emoji }}</span>
                    <span :class="{ 'line-through text-gray-400': task.status === 'completed' }">
                        {{ task.description }}
                    </span>
                </div>
                <button v-if="task.status === 'pending'"
                        @click="$inertia.patch(route('tasks.complete', task.id))"
                        class="text-green-600 hover:text-green-800 text-sm font-medium">
                    ✅ Done
                </button>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-3">🗓️ Upcoming</h2>
            <div v-for="task in upcomingTasks" :key="task.id"
                 class="flex items-center justify-between p-3 mb-2 bg-white rounded-lg shadow-sm border">
                <div>
                    <span class="mr-2">{{ task.category_emoji }}</span>
                    {{ task.description }}
                    <span class="text-sm text-gray-500 ml-2">{{ task.due_label }}</span>
                </div>
            </div>
        </section>

        <div class="mt-8 grid grid-cols-3 gap-4 text-center">
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="text-2xl font-bold">{{ stats.pending }}</div>
                <div class="text-sm text-gray-600">Pending</div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg">
                <div class="text-2xl font-bold">{{ stats.completed_this_week }}</div>
                <div class="text-sm text-gray-600">Done this week</div>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg">
                <div class="text-2xl font-bold">{{ stats.total_messages }}</div>
                <div class="text-sm text-gray-600">Messages</div>
            </div>
        </div>
    </div>
</template>
```

---

## config/services.php

```php
return [
    // ... existing

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    ],
];
```

---

## Reminder Strategy

```
┌──────────┬──────────────────────────────────────────────────────┐
│ Category │ Reminder Schedule                                    │
├──────────┼──────────────────────────────────────────────────────┤
│ 💰 Money │ Evening before 20:00: "Prepare X TL"                 │
│          │ Morning of 07:30:     "Put X TL in backpack"         │
├──────────┼──────────────────────────────────────────────────────┤
│ 🎒 Item  │ Evening before 20:00: "Find and prepare [item]"      │
│          │ Morning of 07:30:     "Pack [item] in backpack"      │
├──────────┼──────────────────────────────────────────────────────┤
│ 📚 HW    │ Sat 10:00:            "Start homework: [desc]"       │
│          │ Sun 18:00:            "Check homework is done"       │
├──────────┼──────────────────────────────────────────────────────┤
│ 📅 Event │ 2 days before 20:00:  "Upcoming: [event] on [day]"   │
│          │ Evening before 20:00: "Tomorrow: [event]"            │
│          │ Morning of 07:30:     "[event] today at [time]!"     │
└──────────┴──────────────────────────────────────────────────────┘
```

---

## Telegram Bot Commands

| Command          | What it does                              |
|------------------|-------------------------------------------|
| (any text)       | Process as school message                 |
| `/start`         | Welcome + instructions                    |
| `/tasks`         | All pending tasks                         |
| `/today`         | Today's tasks                             |
| `/week`          | This week's tasks                         |
| `/done {n}`      | Mark task #n as complete                  |
| `/help`          | Help                                      |

---

## Development with Sail

```bash
./vendor/bin/sail up -d                # Start services
./vendor/bin/sail artisan queue:work   # Queue worker
./vendor/bin/sail artisan schedule:work # Scheduler (dev)
./vendor/bin/sail artisan migrate      # Migrations
./vendor/bin/sail npm run dev          # Vite dev server
```

---

## Production Deployment

```bash
# Cron for scheduler
* * * * * cd /var/www/school-reminder && php artisan schedule:run >> /dev/null 2>&1

# Supervisor for queue
[program:school-reminder-worker]
command=php /var/www/school-reminder/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=2

# Set Telegram webhook
php artisan telegram:set-webhook
# or manually:
# curl -X POST "https://api.telegram.org/bot{TOKEN}/setWebhook" \
#   -d "url=https://yourdomain.com/api/telegram/webhook"

# Build frontend
npm run build
```

---

## Testing with AI SDK Fakes

```php
use App\Ai\Agents\SchoolMessageProcessor;
use Laravel\Ai\Facades\Agent;

it('extracts money task from Turkish message', function () {
    Agent::fake([
        SchoolMessageProcessor::class => [
            'translation_en' => 'Please bring 350 TL for the theater trip by Tuesday.',
            'translation_es' => 'Traigan 350 TL para la excursión al teatro antes del martes.',
            'summary' => 'Money needed for theater trip.',
            'tasks' => [[
                'description' => 'Bring 350 TL for theater trip',
                'category' => 'money',
                'due_date' => '2026-01-14',
                'due_time' => null,
                'amount' => 350,
                'currency' => 'TRY',
                'reminders' => [
                    ['scheduled_at' => '2026-01-13T20:00', 'message' => 'Prepare 350 TL', 'type' => 'preparation'],
                    ['scheduled_at' => '2026-01-14T07:30', 'message' => 'Put 350 TL in backpack', 'type' => 'action'],
                ],
            ]],
        ],
    ]);

    $response = SchoolMessageProcessor::make()->prompt('Değerli veliler...');

    expect($response['tasks'])->toHaveCount(1);
    expect($response['tasks'][0]['category'])->toBe('money');
    expect($response['tasks'][0]['amount'])->toBe(350);
});
```

---

## Sample Turkish Messages for Testing

```
1. "Değerli veliler, Çarşamba günü yapılacak tiyatro gezisi için
    350 TL'nin Salı gününe kadar gönderilmesi gerekmektedir."

2. "Pazartesi günü resim dersi için kırmızı tişört ve önlük
    getirilmesi gerekmektedir."

3. "Hafta sonu ödevi: Matematik kitabı sayfa 45-48 arası
    çözülecek. Pazartesi kontrol edilecektir."

4. "23 Nisan kutlaması için Cuma günü saat 10:00'da okulda
    olunması gerekmektedir. Beyaz gömlek ve siyah pantolon."

5. "Yarın okula gelirken 2 adet A4 kağıdı ve yapıştırıcı
    getiriniz."
```

---

## Future Ideas

- [ ] Photo/image OCR for printed school notices
- [ ] Voice message transcription (AI SDK supports STT)
- [ ] Multi-child support
- [ ] Assign tasks to specific parent
- [ ] Google Calendar sync
- [ ] Monthly cost tracker
- [ ] Recurring pattern detection
- [ ] PWA wrapper for the Vue dashboard
<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/ai (AI) - v0
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v3
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `ai-sdk-development` — TRIGGER when working with ai-sdk which is Laravel official first-party AI SDK. Activate when building, editing AI agents, chatbots, text generation, image generation, audio/TTS, transcription/STT, embeddings, RAG, vector stores, reranking, structured output, streaming, conversation memory, tools, queueing, broadcasting, and provider failover across OpenAI, Anthropic, Gemini, Azure, Groq, xAI, DeepSeek, Mistral, Ollama, ElevenLabs, Cohere, Jina, and VoyageAI. Invoke when the user references ai-sdk, the `Laravel\Ai\` namespace, or this project's AI features — not for other AI packages used directly.
- `fortify-development` — ACTIVATE when the user works on authentication in Laravel. This includes login, registration, password reset, email verification, two-factor authentication (2FA/TOTP/QR codes/recovery codes), profile updates, password confirmation, or any auth-related routes and controllers. Activate when the user mentions Fortify, auth, authentication, login, register, signup, forgot password, verify email, 2FA, or references app/Actions/Fortify/, CreateNewUser, UpdateUserProfileInformation, FortifyServiceProvider, config/fortify.php, or auth guards. Fortify is the frontend-agnostic authentication backend for Laravel that registers all auth routes and controllers. Also activate when building SPA or headless authentication, customizing login redirects, overriding response contracts like LoginResponse, or configuring login throttling. Do NOT activate for Laravel Passport (OAuth2 API tokens), Socialite (OAuth social login), or non-auth Laravel features.
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `wayfinder-development` — Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any React/Vue/Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from @/actions or @/routes, or running wayfinder:generate. Use Wayfinder route functions instead of hardcoded URLs. Covers: wayfinder() vite plugin, .url()/.get()/.post()/.form(), query params, route model binding, tree-shaking. Do not use for backend-only task
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: test()/it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `inertia-vue-development` — Develops Inertia.js v3 Vue client-side applications. Activates when creating Vue pages, forms, or navigation; using <Link>, <Form>, useForm, useHttp, setLayoutProps, or router; working with deferred props, prefetching, optimistic updates, instant visits, or polling; or when user mentions Vue with Inertia, Vue pages, Vue forms, or Vue navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
