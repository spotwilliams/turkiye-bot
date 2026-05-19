<?php

namespace App\Http\Controllers;

use App\Actions\BuildPendingTasksReport;
use App\Actions\CompleteTask;
use App\Actions\RedeemFamilyInvite;
use App\Jobs\ProcessSchoolMessage;
use App\Models\FamilyMember;
use App\Models\Message;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramService $telegram,
        private readonly BuildPendingTasksReport $pendingTasksReport,
        private readonly RedeemFamilyInvite $redeemFamilyInvite,
        private readonly CompleteTask $completeTask,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $messageText = data_get($payload, 'message.text');
        $chatId = data_get($payload, 'message.chat.id');
        $messageId = data_get($payload, 'message.message_id');
        $fromUserId = data_get($payload, 'message.from.id');

        if (! is_string($messageText) || ! is_numeric($chatId) || ! is_numeric($messageId) || ! is_numeric($fromUserId)) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $chatId = (int) $chatId;
        $messageId = (int) $messageId;
        $fromUserId = (int) $fromUserId;

        if ($this->isCommand($messageText, '/start')) {
            return $this->handleStart($messageText, $fromUserId, $chatId);
        }

        if (! FamilyMember::where('telegram_user_id', $fromUserId)->exists()) {
            if (str_starts_with(trim($messageText), '/')) {
                $this->telegram->sendMessage($chatId, 'Not registered. Send `/start <code>` to join.');
            }

            return response()->json(['ok' => true, 'rejected' => 'unknown_sender']);
        }

        if ($this->isCommand($messageText, '/done')) {
            return $this->handleDone($messageText, $chatId);
        }

        if ($this->isCommand($messageText, '/pending')) {
            $report = $this->pendingTasksReport->execute($chatId);
            $this->telegram->sendMessage($chatId, $report);

            return response()->json(['ok' => true, 'command' => 'pending']);
        }

        if (RateLimiter::tooManyAttempts("telegram-ingest:{$fromUserId}", 5)) {
            $this->telegram->sendMessage($chatId, 'Slow down — try again in a moment.');

            return response()->json(['ok' => true, 'rejected' => 'rate_limited']);
        }
        RateLimiter::hit("telegram-ingest:{$fromUserId}", 60);

        $existing = Message::findByText($messageText);

        if ($existing !== null) {
            $this->telegram->sendDuplicateAck($chatId, $existing);

            return response()->json(['ok' => true, 'duplicate' => true, 'message_id' => $existing->id]);
        }

        ProcessSchoolMessage::dispatch($messageText, $chatId, $messageId);

        return response()->json(['ok' => true]);
    }

    private function handleStart(string $text, int $fromUserId, int $chatId): JsonResponse
    {
        $code = trim(substr(trim($text), strlen('/start')));

        if ($code === '') {
            $this->telegram->sendMessage($chatId, 'Usage: /start <code>');

            return response()->json(['ok' => true, 'command' => 'start', 'result' => 'usage']);
        }

        $result = $this->redeemFamilyInvite->execute($code, $fromUserId, $chatId);

        $reply = match ($result->status) {
            'registered' => "Welcome, {$result->familyMember->name}! You're registered. Send school messages or use /pending and /done.",
            'already_registered' => "You're already registered, {$result->familyMember->name}.",
            default => 'Invite code is invalid.',
        };
        $this->telegram->sendMessage($chatId, $reply);

        return response()->json(['ok' => true, 'command' => 'start', 'result' => $result->status]);
    }

    private function handleDone(string $text, int $chatId): JsonResponse
    {
        $arg = trim(substr(trim($text), strlen('/done')));

        if ($arg === '' || ! ctype_digit($arg) || strlen($arg) > 18) {
            $this->telegram->sendMessage($chatId, 'Usage: /done <task id>');

            return response()->json(['ok' => true, 'command' => 'done', 'result' => 'usage']);
        }

        $result = $this->completeTask->execute((int) $arg, $chatId);

        // The "not_found" branch covers both unknown ids and tasks owned by
        // another chat. Same reply for both to avoid leaking task ownership.
        $reply = match ($result->status) {
            'completed' => "✅ \"{$result->task->description}\" marked done. {$result->remaining} pending.",
            'already_done' => "Already done: \"{$result->task->description}\".",
            default => 'Task not found.',
        };
        $this->telegram->sendMessage($chatId, $reply);

        return response()->json(['ok' => true, 'command' => 'done', 'result' => $result->status]);
    }

    private function isCommand(string $text, string $command): bool
    {
        $trimmed = trim($text);

        return $trimmed === $command || str_starts_with($trimmed, $command.' ');
    }
}
