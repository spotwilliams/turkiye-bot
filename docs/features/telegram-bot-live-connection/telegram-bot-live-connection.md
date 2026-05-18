# Title

Telegram Bot Live Connection (MVP)

## Description

Wire the existing Laravel app to a real Telegram bot for the first end-to-end
live test. Adds the security boundary (webhook secret + sender allowlist),
self-service onboarding via single-use invite codes, the minimum set of bot
commands needed to validate the ingest pipeline, and surfaces processing
failures in the admin panel.

### Problem

The webhook endpoint, AI agent, jobs, reminders, and admin panel exist, but the
bot has never been connected to real Telegram traffic. Going live as-is would:

- accept messages from any Telegram user (cost + spam risk),
- offer no onboarding flow for legitimate users (`from.id` must be seeded
  manually),
- silently swallow AI failures with no user feedback and no operator visibility,
- expose `/api/telegram/webhook` to anyone who learns the public URL
  (no secret-token check),
- have no rate limiting, so a paste loop or forward storm bills Claude
  unbounded times.

### Goal

Ship the smallest viable surface to validate one round-trip from Telegram →
Claude → reminders, with:

- closed-by-default access control,
- safe onboarding without manual DB seeding,
- minimum command set (`/start`, `/pending`, `/done`, `/help`),
- visible failures both to user and operator,
- full automated test coverage of every new boundary,
- dev loop runnable from a laptop via ngrok.

### Proposed Behavior

#### Webhook entry — security layers

1. **Middleware `VerifyTelegramWebhook`** validates the
   `X-Telegram-Bot-Api-Secret-Token` header against
   `config('services.telegram.webhook_secret')`. Reject 403 on mismatch.
2. **Form Request `TelegramWebhookRequest`** validates payload shape
   (`message.text` string, `message.chat.id` numeric, `message.from.id`
   numeric, `message.message_id` numeric) and exposes typed accessors
   (`text()`, `chatId()`, `fromUserId()`, `messageId()`). Authorization
   returns true — auth is the middleware's job.
3. **Controller** routes by command and enforces per-action policy
   (allowlist applies to everything except `/start <code>`).

#### Allowlist

- Keyed on `from.id` (user id), not `chat.id`. Replies still go to `chat.id`.
- Schema already supports both columns; no migration needed for the lookup.
- Unknown `from.id`:
  - Message starts with `/` → reply once with
    "Not registered. Send `/start <code>` to join."
  - Plain text → silent 200, no reply, no job dispatched.

#### Onboarding — invite codes

- New table `family_invites`:
  - `code` (string, 64, unique)
  - `name` (string)
  - `role` (string, e.g., `father` / `mother`)
  - `expires_at` (timestamp, nullable — null means no expiry)
  - `used_at` (timestamp, nullable)
  - `used_by_family_member_id` (foreign key to `family_members`, nullable on
    delete)
  - timestamps
- Artisan command: `php artisan family:invite --name="Ricardo" --role=father`
  - Generates a `Str::random(32)` code,
  - Persists row, prints code to the operator,
  - Optional `--expires=24h` flag (deferrable to a later iteration; column
    already in place).
- User sends `/start <code>`:
  - Valid + unused + not expired → create `FamilyMember` with name/role from
    invite, `telegram_user_id` = `from.id`, `telegram_chat_id` = `chat.id`,
    mark invite used + linked, reply with welcome.
  - Already registered `from.id` → idempotent "already registered" reply.
  - Code missing/unknown/expired/used → generic "code invalid" reply
    (no information leak about which case).
  - Malformed (`/start` with no code, or extra args) → usage help.

#### Commands (MVP set)

| Command           | Behavior                                                       |
|-------------------|----------------------------------------------------------------|
| `/start <code>`   | Onboarding. Bypasses allowlist by definition.                  |
| `/pending`        | Existing behavior. Pending tasks for `chat.id`. Show `#id`.    |
| `/done {id}`      | Mark task `{id}` completed. Chat-scoped. Idempotent.           |
| `/help`           | Static usage text.                                             |

Non-command text from an allowlisted user → dispatch
`ProcessSchoolMessage` job (existing path).

#### `/done {id}` rules

- Raw `tasks.id` (matches `docs/features/telegram-mark-task-done.md`).
- Task must belong to current `chat.id`; otherwise reply "task not found"
  (no leak).
- Already-completed task → idempotent "already done" reply.
- Non-numeric or missing id → usage help.
- On success: set `status=completed`, `completed_at=now()`, reply with
  description + remaining pending count.

#### Rate limit

- `RateLimiter` keyed on `from.id`.
- Limit: 5 messages / minute.
- On overflow: reply "Slow down — try again in a moment." No job dispatched.
- Per-`from.id` isolation (one user's overflow does not affect others).

#### Failure handling — visibility for user and operator

- `messages` table gains two columns: `failed_at` (timestamp, nullable),
  `failure_reason` (text, nullable).
- Webhook now creates the `Message` row **immediately** with a `processing`
  state, then dispatches the job. The job updates the same row on success or
  failure (refactor of the current "create-on-success" flow inside
  `ProcessSchoolMessage` action). This aligns with
  `docs/features/ai-processing-failover.md` so the later failover work needs
  no additional schema change.
- `ProcessSchoolMessage::failed()` updates `failed_at` + `failure_reason`
  and sends an apology reply: "Couldn't process this message — please try
  again later."
- Admin `Messages/Index.vue`: status badge column (processed / pending /
  failed).
- Admin `Messages/Show.vue`: when failed, display red callout with
  `failure_reason` and `failed_at` (formatted `Europe/Istanbul`).

#### Artisan tooling

- `telegram:set-webhook` with `--ngrok` flag that auto-detects the public
  URL via `http://localhost:4040/api/tunnels`. Reads
  `TELEGRAM_WEBHOOK_SECRET` from `.env`, registers webhook with
  `secret_token` parameter.
- `telegram:webhook-info` — calls `getWebhookInfo`, prints last error /
  pending count / current URL.
- `telegram:delete-webhook` — for clean state during debugging.
- `family:invite --name --role` — described above.

### Data/Schema Requirements

- New table `family_invites` (columns above).
- New columns on `messages`: `failed_at` (nullable timestamp),
  `failure_reason` (nullable text).
- No change to `family_members`, `tasks`, `reminders` schemas.
- No new indexes needed beyond the unique index on `family_invites.code`.

### UX Requirements

- Welcome (after `/start <code>` success): clear acknowledgement that user is
  now registered, brief reminder of the four commands.
- Invalid code reply: generic, no enumeration of which condition failed.
- `/pending` output: each task line shows `#42  📚  Math homework p.45-48` —
  raw id prominent so `/done 42` is obvious.
- `/done` success: "✅ \"<description>\" marked done. {n} pending."
- `/done` not-found / not-yours / already-done: distinct but non-leaky
  wording.
- Apology on terminal AI failure: short, no stack trace.
- Rate-limit overflow: "Slow down — try again in a moment."

### Parity / Integration Requirements

- Reuse existing `ProcessSchoolMessage` job + `ProcessSchoolMessage` action
  (with the webhook-creates-row refactor noted above).
- Reuse existing `BuildPendingTasksReport` for `/pending` output; adjust
  formatter to surface `#id` clearly.
- Reuse existing `TelegramService` for outbound replies; add helper for
  callback replies if/when buttons are added (out of scope here).
- Existing duplicate-message detection (`Message::findByText`) stays in
  place and runs after the allowlist check.

### AI / Parsing Strategy

- No new AI work in this feature. `SchoolMessageProcessor` agent unchanged.
- Failure of the agent is now visible in the admin panel via the new
  `failed_at` / `failure_reason` columns.

### Idempotency & Concurrency

- `/start` claims an invite under a DB transaction with a row-level lock
  (`lockForUpdate`) to prevent two concurrent claims.
- `/done` is idempotent: completing an already-completed task returns the
  idempotent message and changes nothing.
- Rate limiter is per-`from.id`; no cross-user contention.
- Message-row creation moves to webhook time; ensure unique-by-text
  detection still runs before insert (existing logic).

### Acceptance Criteria

- Telegram bot configured via BotFather; token + secret in `.env`.
- `php artisan telegram:set-webhook --ngrok` registers webhook with secret.
- Sending any text from an unregistered Telegram user produces no DB writes
  and no AI call.
- Sending `/start <valid code>` from an unregistered user creates a
  `FamilyMember` and marks the invite used.
- Subsequent plain-text messages from the now-registered user run through
  the existing pipeline and produce tasks + reminders.
- `/pending` lists those tasks with prominent ids.
- `/done {id}` marks the task completed and replies with remaining count.
- If the AI agent fails, the user receives an apology reply and the admin
  panel shows the failure reason for that message.
- Hitting `/api/telegram/webhook` without the secret header returns 403.
- Hitting the rate limit returns a slow-down reply and no job is dispatched.
- Feature tests cover:
  - **Middleware**: header missing, header wrong, header correct,
    secret not configured.
  - **Form Request**: missing text, missing chat id, missing from id,
    non-string text, non-numeric ids.
  - **Onboarding**: valid unused code, used code, unknown code, expired
    code (if `expires_at` set), already-registered `from.id`, malformed
    `/start`.
  - **Allowlist**: known + plain text dispatches job, known + `/pending`
    works, unknown + plain text silent, unknown + `/` replies with hint.
  - **`/done`**: valid, already done, not found, belongs to other chat,
    non-numeric id, missing id.
  - **Rate limit**: under, over, isolated between users.
  - **Failure path**: job fails 3× → message row marked + apology sent +
    admin sees failure reason.
  - **Existing**: duplicate-message detection + happy path still pass.

## Status

pending

## Commits

- (pending)

## Implementation Notes

(Fill after implementation.)

## Out of Scope (Follow-up Iterations)

- Multi-parent shared family (introduce `families` table; messages, tasks,
  reminders re-keyed off `family_id`; reminders fan out to all members'
  chats). Deferred until single-user MVP validated.
- `/schedule` reschedule command — separate doc already exists
  (`telegram-reschedule-task.md`).
- Inline `Done` / `Snooze` buttons on reminder messages (callback queries).
- Full AI processing failover per `ai-processing-failover.md` (this feature
  implements the schema groundwork only).
- Photo OCR for printed notices, voice message transcription.
- Daily-cap rate limit (only burst limit shipped in MVP).
- Production deployment to Laravel Cloud (dev loop on ngrok only for now).
