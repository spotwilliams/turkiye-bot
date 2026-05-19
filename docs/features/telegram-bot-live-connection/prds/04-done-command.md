# PRD — `/done` Command

**Status: done** (implemented on branch `develop`).

## Problem Statement

Users can paste school messages and receive extracted tasks plus
reminders, but they have no way to mark a task complete from Telegram.
This causes reminder noise (already-handled tasks keep firing) and a
stale `/pending` list.

## Solution

Add `/done {id}` to the Telegram bot. The user reads the task id from
`/pending` (which already returns the list) and sends `/done 42`. The bot
marks the task completed if it belongs to the current chat and replies
with the remaining pending count. Idempotent and chat-scoped to prevent
information leakage about other users' tasks.

This PRD also makes the `/pending` output show the raw task id
prominently so that `/done {id}` is obvious to a user reading the list.

## User Stories

1. As a parent, I want to mark a task complete via `/done {id}`, so that I
   stop receiving reminders for things I have already handled.
2. As a parent, I want `/pending` to show the task id next to each task,
   so that I know exactly what number to pass to `/done`.
3. As a parent, I want a clear confirmation of which task was marked done
   plus how many remain, so that I can keep mental track.
4. As a parent, I want `/done` to be idempotent — running it twice on the
   same task should not error, so that retries are safe.
5. As a parent, I want a clear error when I pass a non-numeric id, so that
   I know I typed the command wrong.
6. As the operator, I want `/done {id}` to refuse to mark a task
   belonging to a different chat, with the same "not found" reply as a
   genuinely missing id, so that one family cannot probe another family's
   task ids.

## Implementation Decisions

- **Command handler in the webhook controller** (or extracted into a
  small `CompleteTaskAction` if the controller starts to bulk out).
  - Parse: `/done` followed by whitespace and digits. Otherwise → usage
    help.
  - Lookup: `Task::where('id', $taskId)->where('telegram_chat_id', $chatId)->first()`.
  - If null → "Task not found." (same reply for missing-id and
    different-chat cases).
  - If status already completed → "Already marked done." (idempotent,
    no DB write).
  - Else → set `status = completed`, `completed_at = now()`, save.
  - Reply with description + remaining pending count.

- **`/pending` output update**
  - Format each line so the id is visually unambiguous, e.g.
    `#42  📚  Math homework pages 45–48`.
  - Change is in the existing `BuildPendingTasksReport` action (or
    formatter); behavior of which tasks are listed does not change.

- **Reminders for the completed task**
  - The scheduled `reminders:process` command already skips reminders
    for completed tasks; no additional change required here.

- **No schema changes.**

## Testing Decisions

A good test sends a webhook payload representing a `/done 42` message
from a real (allowlisted) user, then asserts the database state of the
target task and the outbound Telegram reply. Internals of how the
controller parses the command should not be asserted directly.

Modules under test:

- `/done` happy path: task pending and owned by chat → completed,
  `completed_at` set, success reply.
- `/done` already completed: idempotent reply, no state change.
- `/done` unknown id: "not found" reply.
- `/done` id belonging to another chat: same "not found" reply.
- `/done` non-numeric id: usage help reply.
- `/done` missing id: usage help reply.
- `/pending` formatting: output contains `#{id}` next to each task.

Prior art: existing tests around the `/pending` command path and
`BuildPendingTasksReport`.

## Out of Scope

- `/schedule` reschedule command (separate doc:
  `../telegram-reschedule-task.md`).
- Inline `Done` / `Snooze` buttons on reminder messages (callback
  queries).
- Bulk `/done 1,2,3`.
- Undo / un-complete.

## Further Notes

Task ids grow over time. If a user finds large ids unfriendly, the future
inline-button reminder UX will let them skip typing ids entirely; this
PRD does not optimize for that case.
