# Getting Started

School Task Reminder Bot — local setup and usage guide for humans.

This bot ingests Turkish school messages from Telegram, translates them
into English and Spanish, extracts actionable tasks with due dates,
schedules reminders, and surfaces everything in a web admin panel.

---

## Prerequisites

- PHP 8.3+
- Composer
- Node.js 20+
- Docker (for Laravel Sail) **or** PostgreSQL + Redis running locally
- An Anthropic API key (Claude is the default LLM provider)
- A Telegram bot token (create one with [@BotFather](https://t.me/BotFather))
- ngrok or another HTTPS tunnel for the local Telegram webhook
- For the admin UI: a modern browser

---

## One-time setup

### 1. Clone and install

```bash
git clone <repo>
cd turkiye-bot

composer install
npm install
```

### 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and set:

```env
APP_URL=http://localhost
APP_TIMEZONE=Europe/Istanbul

DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=turkiye_bot
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis
REDIS_PORT=6379

QUEUE_CONNECTION=redis

ANTHROPIC_API_KEY=sk-ant-...

TELEGRAM_BOT_TOKEN=...
TELEGRAM_WEBHOOK_SECRET=<long-random-string>
```

Generate a webhook secret:

```bash
php artisan tinker --execute 'echo \Illuminate\Support\Str::random(40);'
```

### 3. Bring services up

With Sail (Docker):

```bash
./vendor/bin/sail up -d
```

Or run PostgreSQL and Redis yourself and skip Sail.

### 4. Migrate the database

```bash
php artisan migrate
```

### 5. Frontend assets

For development (hot reload):

```bash
npm run dev
```

For production / one-off:

```bash
npm run build
```

### 6. Background workers

In separate terminals:

```bash
php artisan queue:work       # processes ProcessSchoolMessage jobs
php artisan schedule:work    # runs daily digest + reminders dispatch
```

---

## Connecting Telegram

### 1. Create the bot

Talk to [@BotFather](https://t.me/BotFather) on Telegram and create a new
bot. Copy the token into `TELEGRAM_BOT_TOKEN` in `.env`.

### 2. Expose your local app over HTTPS

The Telegram webhook needs a public HTTPS URL. For dev, use ngrok:

```bash
ngrok http 80    # adjust port if not using Sail
```

ngrok prints a URL like `https://abc123.ngrok.io`.

### 3. Register the webhook

```bash
php artisan telegram:set-webhook --ngrok
```

`--ngrok` auto-detects the local ngrok agent at `http://localhost:4040`
and registers the corresponding HTTPS tunnel.

Or pass an explicit URL (cloudflared, expose, etc.):

```bash
php artisan telegram:set-webhook --url=https://my-tunnel.test/api/telegram/webhook
```

### 4. Verify

```bash
php artisan telegram:webhook-info
```

Prints the URL Telegram has on file, plus pending update count and last
error.

To wipe the webhook (useful when switching tunnels or debugging):

```bash
php artisan telegram:delete-webhook
```

### 5. Onboard yourself

The bot is closed by default — unknown senders are silently ignored.
Generate a single-use invite code:

```bash
php artisan family:invite --name="Ricardo" --role=father
```

Optionally add an expiry:

```bash
php artisan family:invite --name="Mara" --role=mother --expires-in-hours=24
```

The command prints a code. Open Telegram, message your bot:

```
/start <code>
```

You are now registered.

---

## Daily usage (Telegram)

Just paste school messages into Telegram. The bot replies with the
translation, summary, and the list of tasks it extracted with their
reminders.

Bot commands:

| Command          | What it does                                            |
|------------------|---------------------------------------------------------|
| (any text)       | Process as a school message (Turkish OK).               |
| `/start <code>`  | Register yourself with an invite code.                  |
| `/pending`       | List tasks still pending for your chat.                 |
| `/done <id>`     | Mark a task complete. Get the id from `/pending`.       |

Reminders fire automatically based on each task's category (money, item,
homework, event) — see the PRD docs under `docs/features/` for the
reminder schedule.

---

## Local CLI tools

Useful artisan commands beyond the standard Laravel set:

| Command                            | Purpose                                                                       |
|------------------------------------|-------------------------------------------------------------------------------|
| `message:new "<text>"`             | Pipe a Turkish message through the AI agent without Telegram. Prints a table. |
| `message:new --from-file=path.txt` | Same, but read text from a file.                                              |
| `digest:send`                      | Send the 08:00 daily digest immediately (the scheduler runs this for you).    |
| `reminders:process`                | Send all reminders whose `scheduled_at` is due (scheduler runs every 30 min). |
| `family:invite`                    | Generate a single-use invite code. See above.                                 |
| `telegram:set-webhook`             | Register the webhook with Telegram. See above.                                |
| `telegram:webhook-info`            | Inspect Telegram's view of the webhook.                                       |
| `telegram:delete-webhook`          | Remove the webhook.                                                           |

---

## Web admin panel

Visit `http://localhost` (or your `APP_URL`) and log in. The admin panel
is gated behind Fortify authentication — register a user, verify your
email, and log in.

Pages:

| Route                                  | Purpose                                                              |
|----------------------------------------|----------------------------------------------------------------------|
| `/`                                    | Public welcome page.                                                 |
| `/dashboard`                           | Default landing page after login.                                    |
| `/admin/messages`                      | All ingested school messages, with status badge (pending / processed / failed) and per-message task count. Filter by chat id or processing state. |
| `/admin/messages/{id}`                 | One message in detail: Turkish original, English + Spanish translation, summary, every task it produced, and every reminder under each task. Failed messages show a red callout with the AI failure reason. |
| `/admin/tasks`                         | All tasks across chats, with status, category, due date, assignee.   |
| `/admin/tasks/{id}`                    | One task in detail with its source message and reminders.            |
| `/admin/reminders`                     | All reminders, with `scheduled_at`, sent flag, and link to the task. |
| `/admin/reminders/{id}`                | One reminder in detail.                                              |
| `/admin/family-members`                | Registered family members (whoever redeemed an invite).              |
| `/admin/family-members/{id}`           | One member in detail.                                                |
| `/settings/*`                          | Profile, password, two-factor, appearance (Fortify defaults).        |

---

## Testing

```bash
php artisan test --compact
```

Filter to a single file or test name:

```bash
php artisan test --compact --filter=TelegramOnboardingTest
php artisan test --compact --filter='message processing'
```

---

## Production deployment

This repo is ready for Laravel Cloud. For self-hosted setups, see the
notes block in `CLAUDE.md`. Key points:

- Cron entry: `* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1`
- A supervisor program for `php artisan queue:work redis --tries=3`
- Run `npm run build` once during deploy.
- Register the production webhook:
  `php artisan telegram:set-webhook --url=https://your-domain/api/telegram/webhook`

---

## Where to look next

- `docs/features/` — one folder per shipped feature, plus PRDs under
  `docs/features/telegram-bot-live-connection/prds/`.
- `CLAUDE.md` — full project blueprint, schema, agent prompt, reminder
  strategy.
- `docs/adr/` — architecture decision records (if present).
