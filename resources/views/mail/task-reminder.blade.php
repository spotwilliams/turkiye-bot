<x-mail::message>
# Reminder

{{ $reminderMessage }}

**Task:** {{ $task->description }}
**Due:** {{ $task->due_date?->format('l, M j') }}

Open the dashboard to mark it done or reschedule.
</x-mail::message>
