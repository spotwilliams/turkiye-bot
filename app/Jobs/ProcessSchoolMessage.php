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
        public ?int $createdBy = null,
    ) {}

    public function handle(ProcessSchoolMessageAction $action, TelegramService $telegram): void
    {
        $message = $action->execute($this->message, $this->createdBy);

        // Web-origin messages have no chat to reply to; their confirmation is
        // the dashboard reflecting the processed state.
        if ($message->telegram_chat_id !== null) {
            $telegram->sendProcessedConfirmation((int) $message->telegram_chat_id, $message, $message->tasks->count());
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->message->refresh();
        $this->message->update([
            'failed_at' => now(),
            'failure_reason' => substr($exception->getMessage(), 0, 1000),
        ]);

        if ($this->message->telegram_chat_id !== null) {
            app(TelegramService::class)->sendMessage(
                (int) $this->message->telegram_chat_id,
                "Couldn't process this message — please try again later."
            );
        }

        report($exception);
    }
}
