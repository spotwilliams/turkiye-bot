# Title

Telegram Reschedule Task

## Description

Allow a user to reschedule an existing task from Telegram using:

- `/schedule {taskId} {date}`
- `/schedule {taskId} {days}`
- `/schedule {taskId} {natural language}` (example: `next saturday`)

### Problem

Real life changes quickly; due dates often need adjustment. Without an in-chat
reschedule flow, users must recreate tasks or accept incorrect reminders.
Natural-language requests (like "next saturday") also require robust date
parsing.

### Goal

Implement `/schedule` to safely update a task's due date/time, recalculate
reminders, and confirm the new schedule in human-friendly language.

### Proposed Behavior

- User sends command variants like:
  - `/schedule 123 2026-05-01`
  - `/schedule 123 +3d`
  - `/schedule 123 3 days`
  - `/schedule 123 next saturday`
- System validates:
  - command includes task id and schedule input,
  - task exists and belongs to current `telegram_chat_id`,
  - task is not completed/cancelled (or define allowed override).
- Parse schedule target:
  - exact date format support (`YYYY-MM-DD`, optionally `DD/MM/YY`),
  - relative days support (`+N`, `N days`),
  - natural language support via a dedicated parser/AI agent.
- Apply update:
  - update `due_date` and optional `due_time`,
  - remove/replace pending reminders tied to old due date,
  - regenerate reminders based on task category rules,
  - persist audit metadata (who changed, when, original due date).
- Respond with confirmation:
  - old due date -> new due date,
  - updated relative countdown,
  - number of reminders re-scheduled.

### AI/NLP Parsing Strategy

- Introduce a focused parser component (recommended):
  - `ScheduleDateParser` service or `RescheduleInterpreter` AI agent.
- Responsibility:
  - convert user input to normalized datetime in app timezone.
- Output contract (structured):
  - `resolved_date` (required),
  - `resolved_time` (nullable),
  - `confidence` (optional),
  - `explanation` (optional for debugging/logging).
- Fallback behavior:
  - if parse fails, return usage guidance with accepted formats.

### Telegram UX Requirements

- Success:
  - `✅ Task (123) rescheduled from 26/04/26 to 03/05/26 (10 days remaining).`
- Parse error:
  - `❌ I couldn't understand the date. Try: /schedule 123 2026-05-03 or /schedule 123 3 days`
- Invalid format:
  - `Usage: /schedule {taskId} {date|days|natural language}`
- Not found/unauthorized:
  - `❌ Task (123) not found in your list.`

### Data/State Requirements

- Update task due fields atomically.
- Maintain old due date in history/audit (table or activity log).
- Recreate only unsent reminders; keep sent reminder history intact.
- Keep timezone handling deterministic (`Europe/Istanbul` default).

### Acceptance Criteria

- `/schedule` updates due date for the selected task in same chat.
- Supports explicit date, relative days, and natural-language date inputs.
- Reminders are recalculated and persisted for the new due date.
- Completed tasks cannot be rescheduled unless explicitly allowed by policy.
- Parse failures produce clear, actionable guidance.
- Feature tests cover:
  - ISO date input,
  - relative-days input,
  - natural-language input (`next saturday`),
  - parse failure path,
  - cross-chat isolation,
  - reminder regeneration correctness.

## Status

pending

## Commits

- (pending)

