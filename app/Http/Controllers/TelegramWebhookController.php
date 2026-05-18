<?php

namespace App\Http\Controllers;

use App\Actions\BuildPendingTasksReport;
use App\Actions\RedeemFamilyInvite;
use App\Jobs\ProcessSchoolMessage;
use App\Models\Message;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramService $telegram,
        private readonly BuildPendingTasksReport $pendingTasksReport,
        private readonly RedeemFamilyInvite $redeemFamilyInvite,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        // TODO: use a custom Request to check/validate who is he sender of the message.
        //      We should only accept messages from some registered telegram accounts. Not everyone can send uss messages via this bot.
        //      Check if telegram validates who can use it. If not, implement a gate
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
            $code = trim(substr(trim($messageText), strlen('/start')));

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

        if ($this->isCommand($messageText, '/pending')) {
            $report = $this->pendingTasksReport->execute($chatId);
            $this->telegram->sendMessage($chatId, $report);

            return response()->json(['ok' => true, 'command' => 'pending']);
        }

        $existing = Message::findByText($messageText);

        if ($existing !== null) {
            $this->telegram->sendDuplicateAck($chatId, $existing);

            return response()->json(['ok' => true, 'duplicate' => true, 'message_id' => $existing->id]);
        }

        ProcessSchoolMessage::dispatch($messageText, $chatId, $messageId);

        return response()->json(['ok' => true]);
    }

    private function isCommand(string $text, string $command): bool
    {
        $trimmed = trim($text);

        return $trimmed === $command || str_starts_with($trimmed, $command.' ');
    }
}
