<?php

namespace App\Jobs;

use App\Actions\ProcessSchoolMessage as ProcessSchoolMessageAction;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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

    public function handle(ProcessSchoolMessageAction $action, TelegramService $telegram): void
    {
        $message = $action->execute($this->messageText, $this->chatId, $this->messageId);

        $telegram->sendProcessedConfirmation($this->chatId, $message, $message->tasks->count());
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
