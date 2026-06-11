# Title

Web Action Surface — Schema & Access Gate

## Problem Statement

As an operator/parent, I want to act on bot data from the browser without
Telegram being live, but the data model and auth layer assume Telegram. Tasks
and messages require a `telegram_chat_id`; there is no way to attribute a
web-created task to a web user for later email delivery; and public
registration is currently enabled, so anyone could sign up and see the whole
family's school data.

## Solution

Lay the schema and access foundation for the web write surface:

- make `telegram_*` columns nullable so web-origin rows can exist without a chat,
- add a nullable `tasks.created_by` foreign key to `users` for delivery
  attribution,
- disable public Fortify registration and provide an artisan command to seed
  parent accounts.

## User Stories

1. As a parent, I want to paste a school message on the web without having a
   Telegram chat, so that a task can be created with no `telegram_chat_id`.
2. As a parent, I want a web-created task to remember that I created it, so that
   its reminders can be emailed to me later.
3. As an operator, I want public sign-up turned off, so that strangers cannot
   register and read or edit my family's school data.
4. As an operator, I want a command to create parent accounts from the CLI, so
   that I can onboard the two parents without a public registration form.
5. As an operator, I want existing Telegram-origin rows to keep working
   unchanged, so that making columns nullable does not regress the live-ish bot
   flow.
6. As a developer, I want `created_by` to be attribution only (not a scope key),
   so that the flat shared-workspace model is preserved.

## Implementation Decisions

- **Migration — `tasks` table:** add `created_by` as a nullable
  `foreignId` referencing `users`, `nullOnDelete`. It is delivery attribution
  only; no query scopes by it.
- **Migration — nullable `telegram_*`:** `tasks.telegram_chat_id`,
  `messages.telegram_chat_id`, and `family_members.telegram_chat_id` become
  nullable. No columns are dropped. Existing rows keep their values; backfill is
  not required because no re-keying happens.
- **No `member_id`, no `families`/`teams` table** in this PRD (see overview
  decisions 1, 2, 8).
- **Access gate:** disable the Fortify registration feature
  (`config/fortify.php` / `FortifyServiceProvider`). The `Welcome` page's
  `canRegister` prop will resolve to false and the registration route becomes
  unavailable.
- **Seed command:** a new artisan command (e.g. `user:create`) prompts for / accepts
  name, email, and password and creates a verified `User`. It is the only way to
  mint web accounts. Idempotent on duplicate email (clear error, no duplicate).
- **No schema change** to `reminders`; channel resolution (PRD 03) reads
  `tasks.telegram_chat_id` / `tasks.created_by`.

## Testing Decisions

A good test asserts externally observable behaviour, not column internals.

- **Web UI flows (feature):** unauthenticated access to any write route
  redirects to login; the registration route is disabled/unavailable; the
  `user:create` command creates a usable, verified account and rejects a
  duplicate email with a clear message.
- **Schema-enabled behaviour (feature):** a task can be persisted with a null
  `telegram_chat_id` and a non-null `created_by`; an existing Telegram-origin
  flow (the duplicate-detection + happy-path tests already in
  `tests/Feature`) still passes unchanged.
- Prior art: `tests/Feature/Admin/*` for auth-redirect assertions and Inertia
  setup; existing Fortify auth tests for registration behaviour.

## Out of Scope

- Email delivery itself (PRD 03).
- Any UI (PRD 05).
- Web invite codes; password reset/2FA changes (Fortify defaults stay).

## Further Notes

`created_by` is deliberately nullable: Telegram-origin tasks have no web user
and leave it null (they resolve to the Telegram channel). This is the column
that makes the "viewing shared, nudging per-creator" asymmetry possible.
