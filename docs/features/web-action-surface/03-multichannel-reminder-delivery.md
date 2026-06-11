# Title

Web Action Surface — Multichannel Reminder Delivery

## Problem Statement

`SendDailyDigest` and `ProcessDueReminders` push exclusively to
`telegram_chat_id` via `TelegramService`, and `SendDailyDigest` iterates
`family_members` chat ids. A web-created task has a null `telegram_chat_id` and
no `family_member` row, so its reminders and the daily digest have nowhere to
go. The product's whole value — proactively nudging so nothing is forgotten —
silently fails for web-origin tasks.

## Solution

Introduce a `ReminderNotifier` that resolves the delivery channel per task and
dispatches accordingly: Telegram for tasks with a `telegram_chat_id`, email to
the task creator otherwise. Add a `MailChannel` alongside a `TelegramChannel`
(wrapping the existing `TelegramService`). Rewrite `ProcessDueReminders` and
`SendDailyDigest` to route through the notifier instead of assuming Telegram.

## User Stories

1. As a web parent, I want to receive my task reminders by email, so that I am
   nudged even though I do not use Telegram.
2. As a web parent, I want a daily email digest of today/tomorrow/this-week
   tasks, so that I get the same morning briefing Telegram users get.
3. As a Telegram parent, I want my reminders and digest to keep arriving in
   Telegram unchanged, so that adding email does not regress my experience.
4. As the creator of a web task, I want its reminder emailed to me specifically,
   so that delivery is attributed to the person who entered it.
5. As an operator, I want the channel chosen automatically from the task's data,
   so that I never have to pick a channel manually.
6. As a parent, I want a completed or cancelled task to stop reminding me on any
   channel, so that I do not get noise for finished work.
7. As a developer, I want delivery behind one interface, so that a future
   `Channel` model or push channel can be added without touching the digest and
   due-reminder commands again.

## Implementation Decisions

- **`ReminderNotifier` (deep module).** Resolves channel per task:
  - `task.telegram_chat_id` present → `TelegramChannel`.
  - else `task.created_by` present → `MailChannel` (email that user).
  - else → no delivery (logged; should not occur).
- **`TelegramChannel`** wraps the existing `TelegramService::sendReminder` /
  `sendMessage`; behaviour identical to today.
- **`MailChannel`** sends a queued `Mailable` to the creator's email address.
  Two mailables: a single-reminder mail and a daily-digest mail.
- **`ProcessDueReminders` rewrite:** for each due unsent reminder whose task is
  still pending, resolve the channel via the notifier and dispatch; mark
  `sent`/`sent_at` as today. Completed/cancelled tasks short-circuit (mark sent,
  no delivery) — same rule as today, now channel-agnostic.
- **`SendDailyDigest` rewrite:** build the today/tomorrow/this-week grouping per
  recipient. Telegram recipients = distinct `family_members` chat ids (as
  today). Email recipients = distinct `users` referenced by `created_by` on
  pending tasks. Each recipient gets one digest on their channel. Empty-state
  message preserved.
- **No `Channel` model** — resolution is inline logic now (overview decision 4).
- Delivery is queued; failures are logged and do not block other recipients.

## Testing Decisions

A good test asserts which channel a task routes to and that the right recipient
is targeted — using `Mail::fake()` and a faked Telegram sender, not real I/O.

- **`ReminderNotifier` + channels (feature) — primary target:**
  - task with `telegram_chat_id` → Telegram send, no mail.
  - task with null `telegram_chat_id` + `created_by` → mail to that user, no
    Telegram send.
  - `ProcessDueReminders` routes each due reminder to the correct channel.
  - completed/cancelled task → no send on any channel, reminder marked sent.
  - `SendDailyDigest` sends a Telegram digest to chat-id recipients and an email
    digest to `created_by` users; empty state yields the "all set" message.
- Prior art: existing `ProcessDueReminders` / `SendDailyDigest` tests;
  Laravel `Mail::fake` patterns.

## Out of Scope

- Web push / PWA channel.
- A persisted `Channel` model or per-user channel preferences.
- Reminder *generation* (PRD 02) and the actions that trigger regeneration
  (PRD 04).

## Further Notes

The notifier interface is the seam the future `Channel` model plugs into:
resolution becomes "look up the recipient's channels" instead of "inspect the
task's columns", with no change to the digest/due-reminder callers.
