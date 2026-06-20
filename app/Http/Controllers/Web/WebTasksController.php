<?php

namespace App\Http\Controllers\Web;

use App\Actions\CancelTask;
use App\Actions\CompleteTask;
use App\Actions\EditTask;
use App\Actions\RescheduleTask;
use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebTasksController extends Controller
{
    public function complete(Task $task, CompleteTask $action): RedirectResponse
    {
        $action->complete($task);

        return $this->toastBack('Task marked done.');
    }

    public function reschedule(Request $request, Task $task, RescheduleTask $action): RedirectResponse
    {
        $validated = $request->validate([
            'due_date' => ['required', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
        ]);

        $action->execute($task, $validated['due_date'], $validated['due_time'] ?? null);

        return $this->toastBack('Task rescheduled.');
    }

    public function update(Request $request, Task $task, EditTask $action): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'in:money,homework,item,event,other'],
            'due_date' => ['sometimes', 'date'],
            'due_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'amount' => ['sometimes', 'nullable', 'numeric'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'assigned_to' => ['sometimes', 'string'],
        ]);

        $action->execute($task, $validated);

        return $this->toastBack('Task updated.');
    }

    public function destroy(Task $task, CancelTask $action): RedirectResponse
    {
        $action->execute($task);

        return $this->toastBack('Task deleted.');
    }

    private function toastBack(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
