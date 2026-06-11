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
}
