# Title

Telegram Mark Task Done

## Description

Allow a user to complete a task from Telegram using `/done {reference task id}`.

### Problem

Users can receive and review tasks, but without a fast completion command they
cannot keep their list accurate in real time. This causes reminder noise and
stale pending lists.

### Goal

Implement `/done {taskId}` so the bot marks the task as completed for the
current chat and confirms the update clearly.

### Proposed Behavior

- User sends `/done {taskId}` (example: `/done 123`).
- System validates command format and numeric task id.
- System fetches the task by:
  - `id = {taskId}`,
  - `telegram_chat_id = current chat`,
  - optional safety check: status not already terminal.
- If valid and pending:
  - set `status = completed`,
  - set `completed_at = now()`,
  - stop future unsent reminders for this task (or skip during processing),
  - return success message with task description and remaining pending count.
- If task already completed:
  - return idempotent informational message (already done).
- If task is missing or belongs to another chat:
  - return "task not found" style error (no data leakage).

### Telegram UX Requirements

- Success:
  - `✅ Task (123) marked as done: Bring 350 TL`
- Already done:
  - `ℹ️ Task (123) is already marked as done.`
- Invalid format:
  - `Usage: /done {taskId}`
- Not found/unauthorized:
  - `❌ Task (123) not found in your pending list.`

### Data Requirements

- Update `tasks.status` and `tasks.completed_at`.
- Preserve task history; do not hard-delete completed tasks.
- Ensure reminder processing respects completed status.

### Acceptance Criteria

- `/done {taskId}` marks matching pending task as completed.
- Command is chat-scoped; one chat cannot complete another chat's task.
- Repeated `/done` calls are safely idempotent.
- Invalid or missing ids return clear usage/error messages.
- Feature tests cover:
  - success path,
  - already completed path,
  - not found path,
  - cross-chat isolation,
  - reminder behavior after completion.

## Status

pending

## Commits

- (pending)

