# PRD — Message Failure Visibility

**Status: done** (implemented on branch `develop`, uncommitted).

## Problem Statement

When `ProcessSchoolMessage` exhausts its 3 retries, the exception is
reported to logs and the user receives nothing. From the parent's
perspective the bot has silently swallowed the school message; from the
operator's perspective the only trace is `failed_jobs` (no link back to
which Telegram message caused it). There is also no way to see in the
admin panel which messages failed and why.

## Solution

Two changes:

1. Make the `Message` row exist from the moment the webhook accepts the
   text, not after AI processing succeeds. The job updates the same row
   on success or failure. This means every accepted message has a
   permanent record regardless of outcome.
2. Add `failed_at` and `failure_reason` columns to `messages`. On
   terminal job failure, the row is updated, an apology is sent to the
   user, and the admin panel surfaces the failure in both the index
   (status badge) and the show page (red callout with the reason).

This also creates the schema groundwork that the larger
`ai-processing-failover.md` feature will later build on, with no further
migration required.

## User Stories

1. As a parent, I want a clear apology when the bot cannot process my
   message, so that I know to try again or paste it differently.
2. As the operator, I want every Telegram message we accepted to be
   visible in the admin panel, so that I can audit the bot even when AI
   processing fails.
3. As the operator, I want a status badge in the messages index
   distinguishing pending / processed / failed, so that I can scan for
   problems at a glance.
4. As the operator, I want the failure reason on the message detail
   page, so that I can debug without leaving the browser.
5. As the operator, I want the failure timestamp shown in the
   `Europe/Istanbul` timezone, so that it matches every other timestamp
   in the panel.
6. As the operator, I want the existing duplicate-detection and
   processed-confirmation flows to still work, so that this refactor
   does not regress the happy path.

## Implementation Decisions

- **Schema migration**
  - `messages.failed_at` (nullable timestamp)
  - `messages.failure_reason` (nullable text)
  - Optionally an implicit status enum: `processing | processed | failed`,
    derived from `(failed_at, processed_at)`. No new column; the badge
    logic reads both timestamps.

- **Webhook flow refactor**
  - The webhook creates the `Message` row immediately on accepting the
    text (after allowlist + rate limit), with `processed_at = null` and
    `failed_at = null` (state = `processing`).
  - `ProcessSchoolMessage::dispatch(...)` now receives the message id
    rather than the raw text, so the job hydrates the same row instead of
    creating one.
  - Existing duplicate detection (`Message::findByText`) runs before row
    creation; duplicates short-circuit to `sendDuplicateAck` as today.

- **Job success path**
  - The action updates the existing row's `translation_*`, `summary`,
    `raw_llm_response`, `processed_at = now()` instead of `create`-ing a
    new one. Tasks + reminders attach to it as today.

- **Job failure path**
  - `ProcessSchoolMessage::failed(Throwable $e)`:
    - sets `failed_at = now()`, `failure_reason = $e->getMessage()` on
      the message row,
    - sends a short apology via `TelegramService` (literal copy:
      "Couldn't process this message — please try again later."),
    - reports the exception (as today).

- **Admin panel — Messages Index**
  - New status badge column rendering one of: `pending` (yellow),
    `processed` (green), `failed` (red).
  - Existing pagination, sorting, columns unchanged.

- **Admin panel — Messages Show**
  - When the message is failed, render a red callout above the content
    block with `failure_reason` and `failed_at` (in `Europe/Istanbul`).
  - When pending, render a small "still processing" notice.
  - Existing content + tasks + reminders blocks unchanged.

## Testing Decisions

A good test triggers the relevant Telegram flow (or directly the failed
job) and asserts: the database state of the `messages` row, the
Telegram replies sent, and the rendered admin view.

Modules under test:

- Webhook: accepting a new message creates a `processing` row before
  the job runs (assert with `Bus::fake()` to suspend the job).
- Job success: existing row is updated, not duplicated; happy-path
  feature tests for `ProcessSchoolMessage` still pass.
- Job failure (`failed()`): row updated with `failed_at` +
  `failure_reason`; user receives apology; existing reporting still
  fires.
- Admin index: failed message renders with the failed badge.
- Admin show: failed message renders the red callout with the reason
  and timestamp.
- Duplicate detection: still acks duplicates and does not create a new
  row.

Prior art: `tests/Feature/Admin/AdminMessagesTest.php` for the panel
side; existing webhook + job tests for the controller side.

## Out of Scope

- Automated retries beyond Laravel's existing 3 attempts.
- Manual "retry this message" button in the admin panel.
- Provider failover (covered by `ai-processing-failover.md`).
- Email / push / Slack alerts to the operator on failure.

## Further Notes

If `failure_reason` ever contains user PII (unlikely for AI SDK
exceptions, but possible if the SDK echoes input), truncate to ~500
chars and strip newlines before persisting.
