# PRD — Telegram Operator Tooling

## Problem Statement

To connect a real Telegram bot to the app, the operator currently has to
craft `curl` calls against the Telegram Bot API by hand. The dev loop is
worse: ngrok issues a new public URL each session, so the operator must
copy that URL into `.env`, then call `setWebhook` manually, every time
they restart the tunnel. There is also no convenient way to inspect the
current webhook state (last error, pending update count) or to wipe the
webhook for clean debugging.

## Solution

Three artisan commands that wrap the Telegram Bot API:

- `telegram:set-webhook --ngrok` — auto-detects the public URL from
  ngrok's local API (`http://localhost:4040/api/tunnels`) and registers
  the webhook with the secret token from `.env`. Falls back to a `--url`
  flag for non-ngrok tunnels.
- `telegram:webhook-info` — calls `getWebhookInfo` and prints current
  URL, last error message, last error timestamp, and pending update
  count.
- `telegram:delete-webhook` — calls `deleteWebhook` for clean state.

## User Stories

1. As the operator, I want a single command to register the webhook
   after starting ngrok, so that I do not copy URLs by hand each
   session.
2. As the operator, I want the command to automatically include the
   secret token from my `.env`, so that the middleware in PRD 1 will
   accept the resulting traffic without further configuration.
3. As the operator, I want to see Telegram's view of my webhook (URL,
   last error, pending count), so that I can debug "why isn't Telegram
   calling me" without guessing.
4. As the operator, I want to wipe the webhook in one command, so that
   I can return the bot to long-polling for local experiments or
   switch tunnels.
5. As the operator, I want a friendly error if ngrok is not running,
   so that I know to start it before retrying.
6. As the operator, I want to pass an explicit `--url` if I am using a
   non-ngrok tunnel (cloudflared, expose), so that the tooling does
   not lock me into ngrok forever.

## Implementation Decisions

- **`telegram:set-webhook` command**
  - Flags: `--ngrok` (auto-detect) or `--url=...` (explicit). Exactly
    one required; error otherwise.
  - With `--ngrok`: HTTP GET `http://localhost:4040/api/tunnels`, pick
    the first HTTPS tunnel's `public_url`, append
    `/api/telegram/webhook`. Error clearly if no HTTPS tunnel found.
  - Reads `TELEGRAM_BOT_TOKEN` and `TELEGRAM_WEBHOOK_SECRET` from
    config; errors out if either is empty.
  - Calls Telegram `setWebhook` with `url` and `secret_token`
    parameters.
  - Prints the URL it registered.

- **`telegram:webhook-info` command**
  - Calls `getWebhookInfo`, prints relevant fields in a small table.

- **`telegram:delete-webhook` command**
  - Calls `deleteWebhook`. Prints success or the Telegram error
    response.

- **Shared HTTP client**
  - Reuse `TelegramService` (already wraps `Http::baseUrl(...)`),
    extend it with `setWebhook`, `getWebhookInfo`, `deleteWebhook`
    methods that the commands call.

- **No schema changes. No config changes beyond what PRD 1 added.**

## Testing Decisions

A good test verifies the artisan command, given crafted ngrok or
Telegram HTTP responses (via `Http::fake()`), produces the right
outbound request and exit code. The test does not hit real Telegram or
real ngrok.

Modules under test:

- `telegram:set-webhook --ngrok`: with a fake ngrok response containing
  an HTTPS tunnel, the command issues a `setWebhook` POST with the
  expected URL and secret. With no tunnel, exits non-zero with a
  friendly message.
- `telegram:set-webhook --url`: command issues `setWebhook` with that
  URL. Errors when both/neither flag provided.
- `telegram:webhook-info`: prints fields from the faked Telegram
  response.
- `telegram:delete-webhook`: issues the correct call and reports
  outcome.

Prior art: existing artisan tests are minimal in this repo; pattern
after Laravel's own `Artisan::call` pattern in feature tests, using
`Http::fake()` for both ngrok and Telegram endpoints.

## Out of Scope

- A web-based equivalent in the admin panel (CLI-only for now).
- Support for tunnels other than ngrok in `--ngrok` auto-detect
  (operator can use `--url` for those).
- Configuring the bot's commands list with Telegram
  (`setMyCommands`) — can be added if the bot starts offering many
  commands.

## Further Notes

ngrok's free tier limits the local agent API to `localhost:4040`. If
the operator runs multiple tunnels, the command picks the first HTTPS
one; document this in the command's `--help` text.
