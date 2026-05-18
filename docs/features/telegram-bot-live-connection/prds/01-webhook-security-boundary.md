# PRD — Webhook Security Boundary

**Status: done** (implemented on branch `develop`).

## Problem Statement

The `/api/telegram/webhook` endpoint is currently open. Anyone who learns the
public URL (or guesses it after deploy) can POST forged Telegram updates and
trigger AI processing jobs at our cost, or inject fake messages attributed to
arbitrary chat ids.

## Solution

Introduce two layers in front of the controller:

1. A middleware that verifies the request actually came from Telegram by
   checking the `X-Telegram-Bot-Api-Secret-Token` header against a secret
   we configured when registering the webhook.
2. A form request that validates the payload shape and exposes typed
   accessors so downstream code never reads raw `data_get` calls again.

Sender allowlist and per-action policy live in separate PRDs (3, 4); this
PRD only proves "this request is from Telegram and well-formed."

## User Stories

1. As the bot operator, I want forged POSTs to `/api/telegram/webhook` to
   be rejected, so that attackers cannot drain my AI budget.
2. As the bot operator, I want to know immediately when the secret token is
   misconfigured, so that I don't silently accept anonymous traffic.
3. As a developer, I want the webhook controller to receive a typed,
   validated payload, so that I don't write `data_get` + `is_numeric`
   checks in every handler.
4. As a developer, I want the security check to be a middleware, so that I
   can reuse it on future Telegram endpoints without copy-pasting the
   header check.
5. As a developer, I want the security check easy to disable in feature
   tests, so that I can test controller behavior without crafting valid
   secret headers in every test.
6. As a developer, I want a single source of truth for the secret token,
   so that the `setWebhook` artisan command and the middleware can't drift.

## Implementation Decisions

- **`VerifyTelegramWebhook` middleware**
  - Reads expected secret from `config('services.telegram.webhook_secret')`.
  - Compares against the `X-Telegram-Bot-Api-Secret-Token` request header
    in constant time.
  - Returns 403 on missing / wrong header.
  - If the configured secret is empty, fail closed (503 or 500) — never
    silently accept anonymous traffic.
  - Registered on the route, not globally. The route file is the only
    place that wires it.

- **`TelegramWebhookRequest` form request**
  - `authorize()` returns true. Authentication is the middleware's job.
  - `rules()` validates: `message.text` is string, `message.chat.id` is
    integer, `message.from.id` is integer, `message.message_id` is integer.
  - Treats non-text updates (stickers, photos, callbacks today) as
    "ignored" — the controller short-circuits with 200 OK so Telegram
    does not retry.
  - Exposes accessors: `text()`, `chatId()`, `fromUserId()`,
    `messageId()`. Controllers and downstream actions consume those, not
    the raw array.

- **Configuration**
  - Add `services.telegram.webhook_secret` reading
    `TELEGRAM_WEBHOOK_SECRET` env.
  - `.env.example` documents the variable.

- **No schema changes.**

## Testing Decisions

A good test exercises observable HTTP behavior (status code, response body,
side effects like a dispatched job) — not internal middleware ordering or
private methods.

Modules under test:

- `VerifyTelegramWebhook` middleware: header missing → 403, header wrong →
  403, header correct → passes through, secret unconfigured → 5xx.
- `TelegramWebhookRequest`: missing `text` / `chat.id` / `from.id` /
  `message_id` → controller returns ignored 200 with no job dispatched.
  Non-string text and non-numeric ids → same outcome.

Prior art: existing tests in `tests/Feature/Admin/` use Pest feature-test
style with `RefreshDatabase`. Follow the same convention. Use `Bus::fake()`
to assert `ProcessSchoolMessage` is or isn't dispatched.

## Out of Scope

- Sender allowlist (PRD 3).
- IP allowlisting against Telegram's published ranges.
- Webhook signature verification beyond the secret-token header (Telegram
  does not currently sign payloads).

## Further Notes

Telegram supports `secret_token` of up to 256 characters, `A-Z`, `a-z`,
`0-9`, `_`, `-`. Generate with `Str::random(40)`.
