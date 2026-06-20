# Title

Web Action Surface — Write UI

## Problem Statement

The admin panel (Messages, Tasks, Reminders, Family) is read-only: a logged-in
user can inspect everything the bot ingested but cannot do anything from the
browser. With the actions (PRD 04), schema (PRD 01), reminder generator
(PRD 02), and delivery (PRD 03) in place, there is still no interface to paste a
message or to complete/reschedule/edit/cancel/snooze from the web.

## Solution

Turn the read-only panel into a read-write surface. Add a paste-message form
that dispatches the existing `ProcessSchoolMessage` job (async, like the
webhook), and wire write controls — done, reschedule, edit, delete, snooze —
into the existing task and reminder pages, each calling the PRD 04 actions.

## User Stories

1. As a parent, I want a textarea to paste a Turkish school message, so that the
   bot processes it the same way as the Telegram path.
2. As a parent, I want to see a pasted message move through
   processing → processed → failed, so that I know whether it worked.
3. As a parent, I want a failed message to show its failure reason, so that I
   know to retry.
4. As a parent, I want a "Done" button on each task, so that I can complete it
   in one click.
5. As a parent, I want a date picker to reschedule a task, so that I can change
   its due date without typing a format.
6. As a parent, I want to edit a task's fields inline or in a form, so that I can
   correct description, category, due date/time, amount.
7. As a parent, I want a "Delete" control that soft-cancels a task, so that it
   leaves my list while keeping history.
8. As a parent, I want a "Snooze" control on a reminder with preset/later
   options, so that I can push a nudge to a better time.
9. As a parent, I want all write controls behind login, so that only seeded
   accounts can act.
10. As a parent, I want the task list to reflect changes immediately after an
    action, so that the UI stays trustworthy.
11. As a parent, I want clear success/error feedback after each action, so that
    I know it took effect.

## Implementation Decisions

- **Stack:** Inertia v3 + Vue 3 + Tailwind v4, matching the existing
  `resources/js/pages/Admin/*`. Reuse existing components (`TaskCard`,
  `CategoryBadge`, `StatusPill`, `AssignedBadge`, `ReminderItem`).
- **Paste form:** a new page/section posting to a controller that dispatches
  `ProcessSchoolMessage` (async). Status reflected via the existing
  `processing` / `processed` / `failed` state plus `failed_at` /
  `failure_reason` columns (already shipped). Use Inertia polling or
  deferred props to surface completion; show a pulsing skeleton while pending.
- **Write endpoints:** add controller actions (extending the `Admin/*`
  controllers or new ones) for complete, reschedule, edit, cancel, snooze, each
  calling the corresponding PRD 04 action. Routes under the existing
  `auth`+`verified` group.
- **Wayfinder:** all frontend calls use generated route helpers; no hardcoded
  URLs. Regenerate after adding routes.
- **Reschedule UI:** native date (and optional time) picker → sends a resolved
  date to `RescheduleTask`. No free-text date entry on web.
- **Snooze UI:** presets (+1h, tonight, +1d) plus a custom time → `SnoozeReminder`.
- **Delete UI:** labelled "Delete" but calls `CancelTask` (soft cancel);
  confirm dialog; cancelled tasks remain visible with a cancelled state.
- **Authorization:** routes behind `auth` middleware; the flat shared-workspace
  model means any logged-in user may act on any row (overview decision 1).
- **`created_by`:** the paste-message controller stamps `created_by` = current
  user on resulting tasks (so PRD 03 can email them). Telegram-origin tasks
  leave it null.

## Testing Decisions

A good test drives the controller/route and asserts the action ran and the
response/redirect is correct — Inertia assertions, not DOM internals.

- **Web UI flows (feature) — primary target:**
  - paste form dispatches `ProcessSchoolMessage` and stamps `created_by`.
  - done/reschedule/edit/delete/snooze endpoints invoke the right actions and
    return the expected redirect/Inertia response.
  - unauthenticated access to any write route redirects to login.
  - failed message surfaces `failure_reason` on the detail page.
- Optional browser/smoke test: the task page renders and the write controls
  mount without JS errors.
- Prior art: `tests/Feature/Admin/{AdminMessagesTest, AdminTasksTest,
  AdminRemindersTest}` for Inertia + auth patterns.

## Out of Scope

- Editing/deleting messages; re-triggering AI on a message.
- Global stats/dashboards, deeper search/date-range filters.
- Parent-specific scoped views (flat shared workspace stands until Teams).
- Web push / PWA.

## Further Notes

This PRD is the thin top layer: nearly all behaviour lives in the actions
(PRD 04) and delivery (PRD 03). Keeping controllers thin (resolve input → call
action → respond) preserves parity with the Telegram surface, which calls the
same actions. Update `docs/features/admin-panel-messages-overview.md` to note
the panel is no longer read-only once this ships.
