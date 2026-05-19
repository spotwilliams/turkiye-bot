<?php

namespace App\Jobs;

use App\Actions\ProcessSchoolMessage as ProcessSchoolMessageAction;
use App\Models\Message;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessSchoolMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public Message $message,
    ) {}

    public function handle(ProcessSchoolMessageAction $action, TelegramService $telegram): void
    {
        $message = $action->execute($this->message);

        $telegram->sendProcessedConfirmation((int) $message->telegram_chat_id, $message, $message->tasks->count());
    }

    public function failed(Throwable $exception): void
    {
        $this->message->refresh();
        $this->message->update([
            'failed_at' => now(),
            'failure_reason' => substr($exception->getMessage(), 0, 1000),
        ]);

        app(TelegramService::class)->sendMessage(
            (int) $this->message->telegram_chat_id,
            "Couldn't process this message — please try again later."
        );

        report($exception);
    }
}
