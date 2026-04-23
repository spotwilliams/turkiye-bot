# Title

AI Message Processing Failover

## Description

Implement resilient AI processing for incoming Telegram messages so we always:

1. receive and persist the message in the database,
2. attempt primary AI processing,
3. automatically trigger fallback processing when the primary path fails,
4. keep the user informed in Telegram (`success` or `in progress`),
5. send a final completion message later when processing succeeds.

### Problem

Current flow is mostly a happy path. If AI processing fails, we do not yet have a robust failover strategy and conversation-state messaging strategy.

### Goal

Guarantee that no incoming Telegram message is dropped and every message eventually receives either:

- a completed processing response, or
- a clear in-progress response followed by completion when fallback succeeds.

### Proposed Behavior

- Telegram webhook receives text message.
- Create/store an initial message record immediately (status: `received` / `processing`).
- Dispatch a primary processing job.
- Primary job attempts AI processing with provider/model A.
- If primary succeeds:
  - persist translations/summary/tasks/reminders,
  - mark message as `processed`,
  - send success response to Telegram.
- If primary fails:
  - mark attempt failure metadata (error, attempt counter, timestamp),
  - dispatch fallback processing (same job class in fallback mode, or a dedicated fallback job),
  - send a Telegram "in progress" message if final output is not ready yet.
- Fallback job uses provider/model B (or safer strategy) and retries within bounded limits.
- If fallback succeeds:
  - persist result,
  - mark message as `processed`,
  - send completion message to Telegram conversation.
- If fallback exhausts retries:
  - mark message as `failed`,
  - notify Telegram with a graceful failure message and optional retry guidance.

### Data/State Requirements

- Add explicit processing lifecycle fields to `messages` (if not present), e.g.:
  - `processing_status` (`received`, `processing`, `processed`, `failed`)
  - `processing_attempts`
  - `last_error`
  - `processed_at`
- Persist primary/fallback attempt metadata for observability.
- Ensure idempotency so duplicate Telegram updates do not create duplicate final messages.

### Queue/Job Strategy

- Option A: single job class with mode/strategy argument (`primary`/`fallback`).
- Option B: dedicated jobs (`ProcessSchoolMessagePrimary`, `ProcessSchoolMessageFallback`).
- Either option must:
  - avoid infinite loops,
  - bound retries,
  - maintain deterministic state transitions,
  - call Telegram updates at the right moment.

### Telegram UX Requirements

- On immediate webhook receive:
  - acknowledge receipt quickly.
- On primary failure + fallback dispatch:
  - send a short "we are still working on this" message.
- On eventual success:
  - send final summary/tasks response.
- On terminal failure:
  - send a concise apology + ask user to retry later.

### Acceptance Criteria

- Incoming messages are persisted before AI processing.
- Primary AI failure triggers fallback automatically.
- Fallback can complete processing and notify user.
- Message status transitions are queryable and auditable.
- Duplicate update handling does not duplicate final user notifications.
- Feature tests cover:
  - primary success,
  - primary fail + fallback success,
  - primary fail + fallback fail,
  - correct Telegram notifications for each state.

## Status

pending

## Commits

- (pending)

