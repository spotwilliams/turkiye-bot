# Hostile review — PRD 04 (`/done` Command)

Scope: uncommitted changes on branch `develop`. Files inspected:

- `app/Http/Controllers/TelegramWebhookController.php`
- `app/Actions/BuildPendingTasksReport.php`
- `tests/Feature/TelegramDoneCommandTest.php` (new)
- `tests/Feature/PendingReportFormatTest.php` (new)
- `tests/Feature/TelegramPendingTasksTest.php`

## P0 — Broken

Pass 1 — clean. No crash-on-merge issues found.

## P1 — Bug risk

- **TelegramWebhookController.php:130** — `$task->update(['status' => 'completed', ...])` and the subsequent `Task::where(...)->count()` are not wrapped in a DB transaction. Two concurrent `/done` calls from the same chat race on the count: both can complete two different tasks and both reply "N pending" with the same N because each saw the pre-update count. User-visible inconsistency, not data corruption. Fix: `DB::transaction(fn () => [...])` around update + count.
- **TelegramWebhookController.php:123** — status check + update is a TOCTOU window. Two concurrent `/done {sameId}` calls both pass `status === 'completed'` check, both write `completed_at = now()`. End state correct but `completed_at` overwritten on the second call. Use `Task::where('id', $id)->where('status', '!=', 'completed')->update(...)` with affected-rows check, OR `lockForUpdate()` inside a transaction. Same fix as previous item.
- **TelegramWebhookController.php:111** — `ctype_digit($arg)` followed by `(int) $arg` does not guard PHP integer overflow. `/done 99999999999999999999` passes `ctype_digit`, casts to a negative or clipped int, then `Task::where('id', ...)` returns null and the user sees "Task not found." Behavior is benign but the validation is weaker than it looks. Fix: explicit length cap (e.g. `strlen($arg) <= 18`) or `filter_var($arg, FILTER_VALIDATE_INT)`.
- **TelegramWebhookController.php:130** — magic string `'completed'` and `'pending'` repeated across controller, `ProcessDueReminders` (existing) and tests. There is no `App\Enums\TaskStatus` enum (the `app/Enums` directory does not exist on this branch despite being mentioned in CLAUDE.md). Pre-existing pattern but PRD 04 entrenches it further. Introduces silent failure risk on any future typo.
- **BuildPendingTasksReport.php:39** — output format changed from `(id)` to `#id  `. No grep was run before this change to confirm `/pending` output is not consumed elsewhere (e.g. screenshots in docs, copy-paste flows, parent doc). I checked: only `TelegramWebhookController::handle` consumes it, and only via `sendMessage`. Safe — but the diff did not document the check. Flag for discipline.
- **TelegramWebhookController.php** — `/done 5 extra junk` is silently routed to AI processing because `isCommand` requires either exact match or `<cmd> ` prefix with only the id following the space (`ctype_digit` rejects "5 extra junk"). Wait — `isCommand('/done 5 extra junk', '/done')` returns true (starts with `/done `), then `handleDone` extracts `"5 extra junk"`, fails `ctype_digit`, returns usage. OK — but `/done5` (no space) is routed to AI ingest. Low impact; cite-only.

## P2 — Design / DRY / over-engineering

- **TelegramWebhookController.php:103–141** — `handleDone` is 40 lines of pure business logic (lookup, status transition, count, format) embedded in the controller. PRD 04 explicitly anticipates "extracted into a small `CompleteTaskAction` if the controller starts to bulk out." Controller is now ~140 lines with allowlist, rate limit, four command handlers, duplicate detection, and ingest dispatch — that is "bulked out." PRD 02 demonstrated the pattern (`RedeemFamilyInvite` action). PRD 04 ignores it. Extract `CompleteTask` action mirroring `RedeemFamilyInvite`'s `execute(...): Result` shape for parity and standalone unit tests.
- **TelegramWebhookController.php:123,130** — `'completed'` string literal used in both the read and the write. A `markCompleted()` method on the `Task` model (or `App\Actions\CompleteTask::execute`) would centralize the transition, the timestamp, and the future reminder-cancellation logic. Currently any new completion site (admin panel, future API, callback button) re-implements three lines.
- **TelegramWebhookController.php:130** — `$task->update(['completed_at' => now()])` writes the timestamp explicitly; `Task` model has no auto-`completed_at` on status transition. Fragile: future code paths can flip status without setting the timestamp. Should be an `Observer` or model method.
- **handleDone reply strings** — built inline with concatenation/interpolation. PRD 02's `/start` uses a `match()` expression keyed on result status. Different style for the same problem. Pick one.
- **TelegramWebhookController.php:79** — `RateLimiter::hit("telegram-ingest:{$fromUserId}", 60)` charges the rate-limit budget for `/done`, `/pending`, and `/start` alike. PRD 03 framed the limit as protecting the AI pipeline. Cheap command-only traffic shouldn't consume the same budget — a user typing `/done 5` five times in a minute then pasting a real school message gets rejected. Fix: only `hit()` on the ingest path, not commands.
- **TelegramWebhookController.php:23–25** — the TODO comment from before PRD 01–03 ("use a custom Request to check/validate who is the sender") is still in place even though PRD 03 implemented exactly that. Remove or it rots.

## P3 — Nits / weak tests

- **tests/Feature/TelegramDoneCommandTest.php:23–41** — `seedTask` helper re-implements `MessageFactory` + `TaskFactory` (both exist in `database/factories/`). Use the factories. Saves 15 lines, exercises factory drift.
- **tests/Feature/TelegramDoneCommandTest.php:10** + **TelegramAllowlistTest.php:9** + **TelegramOnboardingTest.php:10** — three module-scope helper functions (`postDone`, `postWebhook`, `postStart`) doing essentially the same thing. Pest loads test files globally; these names collide across the suite. Today they work because they are unique strings, but the duplication is real. Extract one helper into `tests/Pest.php` or `tests/Support/`.
- **tests/Feature/TelegramDoneCommandTest.php:62–66** — assertion `expect($reply)->toContain('0 pending')` is tight to wording. Reasonable, but consider asserting `(string) $remaining` separately or via regex `\b0\b.*pending` so a future emoji change does not break the test.
- **tests/Feature/TelegramDoneCommandTest.php — "already-completed" test (~line 75)** — asserts `completed_at->equalTo($completedAt)`. Carbon equality at microsecond precision; relies on the controller not re-touching the column. Pass today, but `equalTo` on microseconds is a brittle anchor. Better: assert status is still `completed` AND `completed_at` is within ±1 second of original (or just `< now()`).
- **tests/Feature/TelegramDoneCommandTest.php — "unknown id" test** — uses `/done 99999`. With auto-increment ids and the seeded message + task, collision is unlikely but technically possible. Use `Task::max('id') + 1` or a deliberately huge id like `999999999`.
- **tests/Feature/PendingReportFormatTest.php** — single task, single message, single chat. Does not exercise the multi-task-per-message grouping that is the core of the formatter. Add at least one extra task with the same `message_id` to assert `#id` shows for each.
- **tests/Feature/TelegramDoneCommandTest.php — mock setup** — every test mocks `TelegramService` with the same `shouldReceive('sendMessage')->once()->andReturnUsing(...)` block. Factor into a Pest helper `captureTelegramReply()` returning a closure + reference, or a `MocksTelegram` trait.
- **TelegramWebhookController.php:117** — `"Task not found."` reply is generic (good — no chat leak), but the JSON returns `result => 'not_found'` for both "unknown id" and "belongs to other chat" cases. That is intentional per PRD ("same reply for missing-id and different-chat cases"). Worth a code comment so a future contributor does not "fix" it.

## Pass 4 — Side effects

- `BuildPendingTasksReport` output consumed only in `TelegramWebhookController::handle`. Verified via grep. Tests updated. Clean for now — but no protection against future consumers parsing `(id)` from logs / docs.
- New `/done` route order is correct: command branch returns before ingest. No regression on existing `/pending`, `/start`, duplicate, or ingest flows.
- No env vars added. No migrations. No queue contract change. No public route URL change.

## Pass 5 — Test coverage gaps

- No concurrency test for the TOCTOU race on `/done`. PRD 04 does not require it but PRD 02 added one for invite claims — inconsistency.
- No test that completing a task suppresses its still-pending reminders. PRD 04 §"Reminders for the completed task" says `reminders:process` already skips completed tasks "no additional change required here" — but no regression test asserts this contract holds across this PRD's changes.
- No test for the rate-limiter interaction with `/done` (the P2 issue above): is `/done` supposed to count against the budget? Whatever the answer, pin it with a test.

## Verdict

**Block on P1 race conditions and the rate-limit-charges-commands issue.** Both are small fixes:

1. Wrap `handleDone` write+count in `DB::transaction`, OR extract to `App\Actions\CompleteTask` and do it there.
2. Move `RateLimiter::hit(...)` to inside the ingest branch only (after duplicate check, before `ProcessSchoolMessage::dispatch`).

Then address P2: extract `CompleteTask` action, add `Task::markCompleted()` (or equivalent), kill the stale TODO comment, replace magic strings with an enum (cross-PRD cleanup).

P3 cleanup (factory usage, helper consolidation) can land separately.
