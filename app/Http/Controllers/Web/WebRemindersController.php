<?php

namespace App\Http\Controllers\Web;

use App\Actions\SnoozeReminder;
use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebRemindersController extends Controller
{
    public function snooze(Request $request, Reminder $reminder, SnoozeReminder $action): RedirectResponse
    {
        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $action->execute($reminder, $validated['scheduled_at']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reminder snoozed.']);

        return back();
    }
}
