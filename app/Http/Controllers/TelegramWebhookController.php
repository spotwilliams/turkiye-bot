<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSchoolMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // TODO: use a custom Request to check/validate who is he sender of the message.
        //      We should only accept messages from some registered telegram accounts. Not everyone can send uss messages via this bot.
        //      Check if telegram validates who can use it. If not, implement a gate
        $payload = $request->all();
        $messageText = data_get($payload, 'message.text');
        $chatId = data_get($payload, 'message.chat.id');
        $messageId = data_get($payload, 'message.message_id');

        // TODO: review this. Not sure why we're doing this
        if (! is_string($messageText) || ! is_numeric($chatId) || ! is_numeric($messageId)) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        ProcessSchoolMessage::dispatch(
            $messageText,
            (int) $chatId,
            (int) $messageId
        );

        return response()->json(['ok' => true]);
    }
}
