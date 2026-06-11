# Title

Web Action Surface — Task Write Actions

## Problem Statement

The web surface needs to mutate tasks and reminders — reschedule, edit, cancel,
snooze, complete — but the Actions layer only has `CompleteTask` and
`ProcessSchoolMessage`. There is no action to change a due date and regenerate
reminders, edit task fields, soft-cancel a task while preserving history, or
move a single reminder. Without these, the UI (PRD 05) has nothing to call, and
the Telegram `/schedule` command (still pending) has no shared implementation to
reuse.

## Solution

Build the missing task/reminder write actions as deep, surface-agnostic modules
that both web and Telegram call. Reschedule and edit-due/category route through
`GenerateTaskReminders` (PRD 02) to regenerate reminders deterministically.
Delete is a soft cancel. Snooze moves a single reminder.

## User Stories

1. As a parent, I want to reschedule a task to a new date, so that its reminders
   recalculate for the new due date.
2. As a parent, I want to edit a task's description, so that I can fix a typo or
   clarify it.
3. As a parent, I want to edit a task's category, so that its reminders switch
   to the correct timing pattern.
4. As a parent, I want to edit a task's amount/currency, so that a money task
   shows the right figure.
5. As a parent, I want to mark a task done, so that it stops reminding me and
   leaves my pending list.
6. As a parent, I want to delete a task I no longer need, so that it disappears
   from my list without losing the audit record.
7. As a parent, I want a cancelled task's pending reminders to stop, so that I
   am not nudged for something I removed.
8. As a parent, I want to snooze a reminder to a later time, so that I am nudged
   again when it is more convenient.
9. As a developer, I want one reschedule action shared by web and the future
   Telegram `/schedule`, so that both behave identically.
10. As a parent, I want completing an already-completed task to be safe, so that
    a double click does not error.

## Implementation Decisions

- **`RescheduleTask` (new).** Input: task + an already-resolved due date
  (and optional time). **No natural-language parsing** — the web sends a date
  from a picker; Telegram `/schedule` resolves NL → date before calling this
  action. Updates `due_date`/`due_time`, wipes unsent reminders, regenerates via
  `GenerateTaskReminders`. Rejects completed/cancelled tasks (or per policy).
- **`EditTask` (new).** Updates description, category, due date/time, amount,
  currency, assigned_to. If due date or category changed, triggers the same
  wipe-unsent + regenerate path as reschedule. Other field edits leave reminders
  untouched.
- **`CancelTask` (new) — the "delete" action.** Sets `status = cancelled`,
  wipes unsent reminders, **keeps the row** (history). Reuses
  `TaskStatus::Cancelled`. Idempotent on an already-cancelled task.
- **`SnoozeReminder` (new).** Acts on a single reminder row: set `scheduled_at`
  to a picked/preset later time; if the reminder was already sent, set
  `sent = false` so it fires again.
- **`CompleteTask` (existing).** Reused unchanged; idempotent on
  already-completed.
- All actions are surface-agnostic (no HTTP/Telegram coupling) so they are
  callable from controllers, the Telegram controller, and tests.
- Reminder regeneration always preserves sent reminders and only replaces unsent
  ones (PRD 02 contract).

## Testing Decisions

A good test drives the action and asserts the resulting task/reminder state, not
internal calls.

- **Write Actions (feature) — primary target:**
  - `RescheduleTask`: new due date persisted; unsent reminders wiped and
    regenerated for the new date; sent reminders retained; completed/cancelled
    task rejected.
  - `EditTask`: editing category regenerates reminders with the new pattern;
    editing description only leaves reminders untouched.
  - `CancelTask`: status becomes cancelled, unsent reminders wiped, row still
    exists; second cancel is a safe no-op.
  - `SnoozeReminder`: `scheduled_at` moves; an already-sent reminder is reset to
    refire.
  - `CompleteTask`: still passes; double-complete is safe.
- Prior art: existing `CompleteTask` and `BuildPendingTasksReport` tests;
  `tests/Feature/Admin/*` for setup patterns.

## Out of Scope

- The UI that calls these (PRD 05).
- Natural-language date parsing (Telegram `/schedule`, separate doc).
- Editing/deleting messages or re-triggering the AI pipeline.

## Further Notes

`RescheduleTask` taking a resolved date is the key seam: it keeps NL parsing a
Telegram-surface concern and lets the web use a plain date picker, while both
share the persistence + regeneration logic. The pending
`docs/features/telegram-reschedule-task.md` should be updated to call this
action once `/schedule` is built live.
