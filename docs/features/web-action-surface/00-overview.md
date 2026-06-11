# Web Action Surface — Overview

## Problem Statement

The bot's entire value pipeline already exists — the `SchoolMessageProcessor`
agent, the `ProcessSchoolMessage` action, tasks, reminders, the daily digest,
and a read-only admin panel. The only path to *act* on any of it (paste a
message, mark a task done, reschedule, snooze) is Telegram. But the Telegram
bot has never been wired to live traffic (no BotFather token, ngrok-only dev
loop), and going live carries onboarding, rate-limit, and spend risk that the
team is not ready to take on yet.

Meanwhile the admin panel is read-only: an operator can *see* everything the
bot ingested but cannot do anything with it from the browser.

## Solution

Promote the web app from a read-only admin panel to a **co-equal write
surface** over the existing Actions layer. A logged-in user can paste a school
message, and view/complete/reschedule/edit/cancel/snooze tasks and reminders —
all from the browser, without Telegram being live. Telegram stays fully built
and is wired to live traffic in a later iteration; nothing is thrown away.

Both surfaces (web and Telegram) call the same Action classes, so behaviour
stays identical regardless of entry point.

## Architectural Decisions (apply across all PRDs in this set)

These were settled in a design session and bind every PRD below.

1. **Flat shared workspace.** Every logged-in `User` sees and edits *all* data
   (messages, tasks, reminders) — the same god-view the admin panel has today,
   now read-write. There is **no per-user scoping** and **no `member_id`** on
   `messages` or `tasks`.

2. **`users` and `family_members` are decoupled.** `users` (Fortify) is the web
   identity. `family_members` is the Telegram delivery registry only
   (`telegram_chat_id`, `telegram_user_id` for allowlist + reply routing).
   They are **not** linked by a foreign key. Adding a `users.family_member_id`
   link was explicitly rejected as work that the future Teams migration would
   discard.

3. **Global dedup is unchanged.** The existing text-only `normalized_text_hash`
   dedup (one record per unique text, reused for everyone) stays exactly as
   shipped. Because all users see everything, there is no ownership conflict.

4. **`telegram_*` columns become nullable**, never dropped. Web-origin rows
   leave them null. A dedicated `Channel` model (telegram/email/web rows per
   user) is **deferred**; escalate to it only if needed.

5. **PHP owns reminder generation (single source).** The AI agent stops
   emitting reminders. A deterministic `GenerateTaskReminders` module encodes
   the category timing rules and runs at ingest *and* on reschedule/edit.

6. **Reminder delivery is channel-resolved.** A `ReminderNotifier` resolves the
   channel per task: `telegram_chat_id` present → Telegram; otherwise
   `tasks.created_by` present → email that user. A nullable
   `tasks.created_by` FK (→ `users`) carries delivery attribution only — it is
   **not** scoping.

7. **Web access is closed.** Public Fortify registration is disabled. Parent
   accounts are seeded via an artisan command. The web invite-code flow is out
   of scope.

8. **Future target is Jetstream Teams.** When multi-family / proper grouping is
   needed, Teams replaces `family_members` + `family_invites` and rows re-key to
   `team_id`. No work in this set should pre-build toward it.

## PRD Breakdown

| #  | PRD                                          | Core deliverable                                              |
|----|----------------------------------------------|---------------------------------------------------------------|
| 01 | Schema & Access Gate                         | `tasks.created_by`, nullable `telegram_*`, disable registration, `user:create` |
| 02 | Deterministic Reminder Generator             | `GenerateTaskReminders`; AI schema drops reminders; ingest refactor |
| 03 | Multichannel Reminder Delivery               | `ReminderNotifier` + `TelegramChannel`/`MailChannel`; digest + due-reminder rewrite |
| 04 | Web Task Write Actions                       | `RescheduleTask`, `EditTask`, `CancelTask`, `SnoozeReminder` actions |
| 05 | Web Write UI                                 | Inertia controllers/pages/forms for paste + CRUD            |

Recommended build order: 01 → 02 → 03 → 04 → 05. (02 and 04 depend on 01's
columns; 04's reschedule/edit depend on 02's generator; 05 wires the UI to 04.)

## Two Asymmetries (conscious, accepted)

1. **Viewing is shared, nudging is not.** All users see all tasks, but an email
   reminder goes only to the task's creator. A task pasted by parent A never
   emails parent B. Accepted until Teams.

2. **Two reminder origins, one generator.** Telegram-origin tasks (chat_id) and
   web-origin tasks (created_by) both flow through `GenerateTaskReminders`, so
   reminder patterns are identical everywhere.

## Out of Scope (whole set)

- `Channel` model abstraction (telegram/email/web rows per user).
- Jetstream Teams migration / multi-family.
- Web invite-code onboarding.
- Web push / PWA notifications.
- Natural-language date parsing on web (stays a Telegram `/schedule` concern;
  the reschedule action takes an already-resolved date).
- Wiring Telegram to live traffic (separate `telegram-bot-live-connection` set).
