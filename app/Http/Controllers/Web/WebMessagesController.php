<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSchoolMessage;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebMessagesController extends Controller
{
    /**
     * Accept a pasted school message and process it through the same async
     * pipeline as the Telegram webhook, attributing resulting tasks to the
     * current user for email delivery.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'min:1'],
        ]);

        $message = Message::create([
            'telegram_chat_id' => null,
            'original_text' => $validated['text'],
            'normalized_text' => Message::normalizeText($validated['text']),
            'normalized_text_hash' => Message::hashText($validated['text']),
        ]);

        ProcessSchoolMessage::dispatch($message, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Message received — processing.']);

        return back();
    }

    /**
     * Re-run the processing pipeline for a message whose previous run failed.
     * Clears the failure state so the dashboard reflects "processing" again,
     * then re-dispatches the same async job. Web-origin messages are
     * re-attributed to the retrying admin; Telegram-origin messages keep
     * channel delivery (no owner).
     */
    public function retry(Request $request, Message $message): RedirectResponse
    {
        if ($message->failed_at === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only failed messages can be retried.']);

            return back();
        }

        $message->update([
            'failed_at' => null,
            'failure_reason' => null,
        ]);

        $createdBy = $message->telegram_chat_id === null ? $request->user()->id : null;

        ProcessSchoolMessage::dispatch($message, $createdBy);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Retrying — processing.']);

        return back();
    }
}
