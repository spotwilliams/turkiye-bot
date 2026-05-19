<?php

namespace App\Console\Commands;

use App\Actions\ProcessSchoolMessage;
use App\Models\Message;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class NewMessage extends Command
{
    protected $signature = 'message:new
        {text? : The Turkish school message text}
        {--chat-id= : Telegram chat id (numeric). Random if omitted}
        {--message-id= : Telegram message id (numeric). Random if omitted}
        {--from-file= : Load the message text from a file path}
        {--json : Print the persisted message as JSON}';

    protected $description = 'Run the school-message action and print the resulting message, tasks, and reminders';

    public function handle(ProcessSchoolMessage $action): int
    {
        $text = $this->resolveText();
        if ($text === null) {
            return self::FAILURE;
        }

        $chatId = $this->resolveNumericOption('chat-id');
        $messageId = $this->resolveNumericOption('message-id');
        if ($chatId === null || $messageId === null) {
            return self::FAILURE;
        }

        $json = (bool) $this->option('json');

        if (! $json) {
            note("chat_id={$chatId}  message_id={$messageId}");
            note(message: "Input:\n  ".str_replace("\n", "\n  ", $text), type: 'info');
        }

        $existing = Message::findByText($text);
        if ($existing !== null) {
            if (! $json) {
                warning("Duplicate detected — message #{$existing->id} already exists. Showing existing record (no agent call, nothing persisted).");
            }
            $this->render($existing->load('tasks.reminders'));

            return self::SUCCESS;
        }

        $provider = config('ai.default');
        $model = config('ai.default_text_model');
        $startedAt = microtime(true);

        $row = Message::create([
            'telegram_chat_id' => $chatId,
            'telegram_message_id' => $messageId,
            'original_text' => $text,
            'normalized_text' => Message::normalizeText($text),
            'normalized_text_hash' => Message::hashText($text),
        ]);

        try {
            $message = $json
                ? $action->execute($row)
                : spin(
                    fn () => $action->execute($row),
                    "Calling {$provider}/{$model} + persisting...",
                );
        } catch (Throwable $e) {
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $json) {
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            $reminderCount = $message->tasks->sum(fn ($t) => $t->reminders->count());
            info("Finished in {$elapsedMs} ms — persisted 1 message, {$message->tasks->count()} task(s), {$reminderCount} reminder(s)");
        }

        $this->render($message);

        return self::SUCCESS;
    }

    private function render(Message $message): void
    {
        if ($this->option('json')) {
            $this->output->writeln((string) json_encode([
                'message' => $message->toArray(),
                'tasks' => $message->tasks->map(fn ($t) => [
                    ...$t->toArray(),
                    'reminders' => $t->reminders->toArray(),
                ])->all(),
            ], JSON_UNESCAPED_UNICODE));

            return;
        }

        note('Message');
        table(
            ['Field', 'Value'],
            [
                ['ID', (string) $message->id],
                ['Chat ID', (string) $message->telegram_chat_id],
                ['Message ID', (string) $message->telegram_message_id],
                ['Summary', $message->summary],
                ['Translation EN', $message->translation_en],
                ['Translation ES', $message->translation_es],
            ],
        );

        if ($message->tasks->isEmpty()) {
            warning('No tasks extracted — informational message only.');

            return;
        }

        note('Tasks');
        table(
            ['#', 'Category', 'Description', 'Due', 'Amount', 'Reminders'],
            // @phpstan-ignore-next-line
            $message->tasks->values()->map(function ($task, $i) {
                $amount = $task->amount ? "{$task->amount} {$task->currency}" : '—';
                $due = $task->due_date->format('Y-m-d').($task->due_time ? ' '.$task->due_time : '');

                return [
                    $i + 1,
                    $task->category,
                    $task->description,
                    $due,
                    $amount,
                    (string) $task->reminders->count(),
                ];
            })->all(),
        );

        $reminderRows = [];
        foreach ($message->tasks as $i => $task) {
            $n = $i + 1;
            foreach ($task->reminders as $reminder) {
                $reminderRows[] = [
                    $n,
                    $reminder->scheduled_at->format('Y-m-d H:i'),
                    $reminder->type,
                    $reminder->message,
                ];
            }
        }

        if ($reminderRows !== []) {
            note('Reminders');
            // @phpstan-ignore-next-line
            table(['Task #', 'When', 'Type', 'Message'], $reminderRows);
        }
    }

    private function resolveText(): ?string
    {
        $fromFile = $this->option('from-file');
        if (is_string($fromFile) && $fromFile !== '') {
            if (! is_file($fromFile) || ! is_readable($fromFile)) {
                $this->error("Cannot read file: {$fromFile}");

                return null;
            }
            $text = trim((string) file_get_contents($fromFile));
        } else {
            $text = trim((string) $this->argument('text'));
        }

        if ($text === '') {
            $this->error('Message text is required (pass as argument or use --from-file).');

            return null;
        }

        return $text;
    }

    private function resolveNumericOption(string $name): ?int
    {
        $value = $this->option($name);
        if ($value === null || $value === '') {
            return random_int(100000, 999999);
        }
        if (! is_numeric($value)) {
            $this->error("--{$name} must be numeric.");

            return null;
        }

        return (int) $value;
    }
}
