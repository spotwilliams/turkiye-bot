# PRD — Sender Allowlist & Rate Limit

**Status: done** (implemented on branch `develop`).

## Problem Statement

After the security boundary (PRD 1) confirms a request is from Telegram and
after the onboarding flow (PRD 2) creates `FamilyMember` rows for trusted
users, the bot still needs to:

1. reject any message from a Telegram user not on the allowlist, without
   leaking information,
2. protect against accidental floods (paste loops, forward storms) from
   even allowlisted users, because each accepted message costs a Claude
   API call.

## Solution

A per-action policy in the webhook controller that:

- looks up `FamilyMember` by `telegram_user_id = from.id`,
- if not found, replies only when the message starts with `/`
  (giving a fumbling invitee a hint), and is silent otherwise,
- if found, applies a `RateLimiter` keyed on `from.id` with a small burst
  budget before dispatching the ingest job.

`/start <code>` is excluded from the allowlist (PRD 2 handles it).

## User Stories

1. As the operator, I want messages from unknown Telegram users to be
   ignored, so that the bot does not run AI on stranger traffic.
2. As the operator, I want unknown users who send commands (text starting
   with `/`) to get a hint about `/start`, so that a real invitee who
   forgot the code can self-serve.
3. As the operator, I want unknown users sending plain text to receive no
   reply at all, so that the bot cannot be used as a free outbound message
   relay.
4. As the operator, I want a 5-messages-per-minute burst limit per
   `from.id`, so that an accidental paste loop or forward storm does not
   bill Claude unbounded times.
5. As the operator, I want the rate limiter to be per-user, so that one
   user hitting the limit does not throttle another family member.
6. As an allowlisted user, I want a polite "slow down" reply when I hit
   the limit, so that I know my message was dropped on purpose.
7. As the operator, I want the rate-limit overflow to drop the message
   completely (no DB write, no job dispatch), so that the limit actually
   reduces cost.

## Implementation Decisions

- **Allowlist lookup**
  - Single query: `FamilyMember::where('telegram_user_id', $fromId)->exists()`.
  - Cache for the request lifetime is fine; no global cache needed.

- **Unknown sender handling**
  - `/` prefix → reply with onboarding hint (literal copy: "Not
    registered. Send `/start <code>` to join."). Return 200.
  - Plain text → return 200 with no Telegram reply, no DB write, no job.

- **Rate limiter**
  - Laravel's `Illuminate\Support\Facades\RateLimiter`.
  - Key: `"telegram-ingest:{$fromUserId}"`.
  - Limit: 5 attempts per 60 seconds. Configurable via `config('services.telegram.rate_limit')` (count + window).
  - On overflow: send "Slow down — try again in a moment." Return 200,
    no job.

- **Order of operations in the controller**
  1. Form request validated (PRD 1).
  2. If `/start <code>` → onboarding (PRD 2). Return.
  3. Allowlist check. Reject path applies.
  4. Rate limit check. Reject path applies.
  5. Command dispatch (`/pending`, `/done`, `/help`) or plain-text
     ingest (`ProcessSchoolMessage::dispatch`).

## Testing Decisions

A good test crafts a Telegram payload and asserts the visible result:
which reply (if any) was sent via the Telegram service, whether a job was
dispatched, whether a DB row was created. Internals of the rate limiter
should not be asserted directly.

Modules under test:

- Allowlist gate:
  - known + plain text → job dispatched.
  - known + `/pending` → existing handler runs.
  - unknown + plain text → silent (no reply, no job).
  - unknown + command-shaped text → onboarding hint reply, no job.
- Rate limiter:
  - 5 messages from the same `from.id` within one minute → all dispatched.
  - 6th within the same minute → "slow down" reply, no job.
  - user A overflowing does not block user B in the same window.

Use `Bus::fake()` for job assertions and a fake Telegram service (or
`Http::fake()` at the HTTP layer) for outbound-reply assertions. Time
travel with `Carbon::setTestNow()` or the rate-limiter cache store to
reset between sub-cases.

Prior art: existing webhook tests around duplicate detection and the
`/pending` command path.

## Out of Scope

- Daily-cap rate limiting (operator can add later if burst limit alone
  proves insufficient).
- Throttling per-chat versus per-user (we key on `from.id` only).
- Telegram-side spam controls (Telegram's own throttling layer is
  separate and out of our control).

## Further Notes

The 5/min limit is a starting value; the configurable count + window
makes tuning cheap. If real usage shows it is too tight, raising to
10/min is a one-line config change with no code rewrite.
