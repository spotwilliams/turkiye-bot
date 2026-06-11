<?php

namespace App\Http\Controllers\Web;

use App\Actions\SnoozeReminder;
use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WebRemindersController extends Controller
{
    public function snooze(Request $request, Reminder $reminder, SnoozeReminder $action): RedirectResponse
    {
        $validated = $request->validate([
            'scheduled_at' => ['required', 'date'],
        ]);

        $action->execute($reminder, $validated['scheduled_at']);

        return back()->with('status', 'Reminder snoozed.');
    }
}
