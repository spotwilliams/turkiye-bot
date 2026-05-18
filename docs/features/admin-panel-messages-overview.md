# Title

Admin Panel — Messages Overview

## Description

Provide a simple web admin panel for inspecting everything the bot has
ingested: the list of school messages, and for each message, its extracted
tasks and their reminders. This is an internal operator view, not a
parent-facing dashboard.

### Problem

Today the only way to inspect ingested data is via `tinker`, direct DB
queries, or the `message:new` CLI command. There is no browser-based view to:

- confirm the bot is receiving and processing messages correctly,
- audit what the AI agent extracted (summary, translations, tasks, reminders),
- debug unexpected behavior without shelling into the app.

### Goal

Ship a minimal read-only admin panel whose first iteration covers:

1. a paginated list of all messages,
2. a detail view for a single message showing its translations, summary, and
   connected tasks,
3. each task exposes its reminders (scheduled, sent, type).

Write flows (mark done, reschedule, delete) are intentionally out of scope
for this first iteration.

### Scope — First Iteration

In scope:

- Messages index page (list view).
- Message detail page (translations + summary + tasks + reminders).
- Authentication gate (reuse existing Fortify auth — only logged-in users).
- Read-only UI.

Out of scope (future iterations):

- Editing/deleting messages, tasks, reminders.
- Re-triggering the AI pipeline on a message.
- Filtering by chat, family member, or date range beyond trivial pagination.
- Global stats/dashboards.
- Any parent-facing UI (this panel is operator-only).

### Proposed Behavior

#### Messages Index (`/admin/messages`)

- Shows a paginated list of messages ordered by `created_at` desc.
- Each row displays:
  - id,
  - `telegram_chat_id`,
  - truncated `summary` (fallback to truncated `original_text` if summary null),
  - `processed_at` (or "pending" if null),
  - task count,
  - `created_at` in `Europe/Istanbul`.
- Row is clickable and routes to the message detail page.
- Pagination: 25 per page.

#### Message Detail (`/admin/messages/{message}`)

- Header block:
  - id, chat id, created at, processed at.
- Content block:
  - original Turkish text,
  - English translation,
  - Spanish translation,
  - summary.
- Tasks block:
  - each task as a card/row showing:
    - id, description, category, status,
    - due date + due time,
    - amount + currency (if present),
    - assigned_to.
  - nested reminders list per task:
    - scheduled_at, type, sent (bool), sent_at,
    - reminder message text.
- Empty states:
  - "No tasks extracted" when the message has no tasks,
  - "No reminders" when a task has no reminders.

### UI & Tech Requirements

- Follow existing stack conventions (Inertia v3 + Vue 3 + Tailwind v4).
- New pages under `resources/js/pages/Admin/Messages/` (Index.vue, Show.vue).
- New controller under `app/Http/Controllers/Admin/` (e.g.,
  `AdminMessagesController`) returning Inertia responses.
- Use Wayfinder-generated route helpers on the frontend; no hardcoded URLs.
- Use Eloquent API Resources (or inline `->only()` projections) to shape
  payloads — avoid leaking raw model arrays with unused columns.
- Authorization: gate routes behind `auth` middleware; no extra roles yet
  (single trusted operator assumed). Add a policy placeholder so a proper
  role check can be added later without refactoring routes.

### Data/Query Requirements

- Eager load relations to avoid N+1:
  - index: `withCount('tasks')`,
  - show: `with(['tasks.reminders'])`.
- Sort tasks by `due_date` asc, then `due_time` nulls last.
- Sort reminders by `scheduled_at` asc.

### Routing

- `GET /admin/messages` → index.
- `GET /admin/messages/{message}` → show.
- Route names: `admin.messages.index`, `admin.messages.show`.
- Register under `routes/web.php` with `auth` middleware group.

### Acceptance Criteria

- Logged-in user can open `/admin/messages` and see a paginated list of
  messages with task counts.
- Unauthenticated user is redirected to login.
- Clicking a row opens the detail page showing translations, summary,
  tasks, and their reminders.
- Detail page handles the empty states (no tasks; task with no reminders)
  gracefully.
- No N+1 queries on either page (verified via query log or a targeted test).
- Feature tests cover:
  - index renders with messages and returns correct props,
  - show renders the requested message with nested tasks and reminders,
  - unauthenticated access is redirected,
  - 404 when the message id does not exist.

## Status

done (extended beyond first iteration)

## Commits

- (pending)

## Implementation Notes

First iteration (messages index + show) shipped, then extended with:

- `Admin/Tasks` index + show (`AdminTasksController`) with filters by status
  and category. New `TaskRow.vue` component; reuses `TaskCard`, `CategoryBadge`,
  `StatusPill`, `AssignedBadge`, `ReminderItem`.
- `Admin/Reminders` index + show (`AdminRemindersController`) with filters by
  sent state, type, and time window (due now / upcoming). Links back to task
  and source message.
- `Admin/FamilyMembers` index + show (`AdminFamilyMembersController`).
  Read-only view of registered Telegram parents and their preferences JSON.
- Filters added to messages index (`chat_id`, `processed` state).
- Sidebar: Tasks, Reminders, Family Members all enabled.
- Routes: `admin.tasks.{index,show}`, `admin.reminders.{index,show}`,
  `admin.family-members.{index,show}`. Wayfinder helpers regenerated;
  frontend uses `admin.tasks`, `admin.reminders`, `admin.familyMembers`.
- Reference id conventions: `MSG-###`, `TASK-###`, `REM-###`, `FAM-###`.
- Tests: `tests/Feature/Admin/{AdminMessagesTest, AdminTasksTest,
  AdminRemindersTest, AdminFamilyMembersTest}.php` — 29 tests, 254 assertions.

Still out of scope (future iterations): write actions (mark done, delete,
re-trigger AI), global stats, deeper search/date-range filters, role-based
authorization beyond `auth` middleware.
