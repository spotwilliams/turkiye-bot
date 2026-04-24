# Title

Message Processor Test Command

## Description

Add an Artisan command that emulates the Telegram webhook flow so we can test
`ProcessSchoolMessage` from CLI without sending a real Telegram update.

### Problem

Today, testing the message-processing pipeline depends on real webhook payloads
or manual HTTP calls. That slows down local debugging and makes it harder to
quickly test prompts, queue behavior, and persistence with repeatable inputs.

### Goal

Provide a dedicated command that mirrors
`App\Http\Controllers\TelegramWebhookController` behavior for text messages:

1. accept a message and chat/message identifiers as input,
2. emulate webhook ingestion semantics,
3. dispatch (or optionally run) `ProcessSchoolMessage`,
4. keep the same persistence + notification behavior as the webhook path.

### Proposed Behavior

- Create command (example signature):
  - `message:new {text?} {--chat-id=}` `{--message-id=}`
  - optional flags:
    - `--sync` to run job immediately (debug mode),
    - `--from-file=` to load long sample text from file,
    - `--json` to print structured execution output.
- Command should validate required inputs:
  - message text present and non-empty,
  - numeric `chat_id` and `message_id` (or generated defaults).
- Command should follow webhook-equivalent path:
  - normalize input into a webhook-like payload shape,
  - dispatch `ProcessSchoolMessage` with the same arguments used by controller.
- In default mode:
  - queue dispatch and print a queued confirmation in terminal.
- In `--sync` mode:
  - run inline for fast local debugging and print summary of persisted records.
- Output should include:
  - selected chat/message ids,
  - whether dispatch was queued vs sync,
  - success/failure indicator and error message when relevant.

### Parity Requirements with Webhook Flow

- Use the same job class: `App\Jobs\ProcessSchoolMessage`.
- Keep argument mapping identical to controller behavior.
- Do not add alternate persistence rules in command-only path.
- Any future failover logic in the job should naturally apply to this command
  path without extra branching.

### UX / Developer Experience

- Fast smoke-test usage:
  - `php artisan message:new "Yarin gezi icin 350 TL getiriniz."`
- Deterministic test usage:
  - `php artisan message:new --from-file=storage/app/samples/msg1.txt --chat-id=123 --message-id=456 --sync --json`
- Friendly terminal messages for both success and validation errors.

### Testing Requirements

- Feature tests for command should cover:
  - valid input dispatches `ProcessSchoolMessage`,
  - `--sync` path executes and reports completion,
  - invalid/missing input fails with clear error output,
  - `--from-file` input works,
  - command and webhook paths pass equivalent args to the job.
- Existing webhook tests remain intact; new tests assert parity.

### Acceptance Criteria

- Command exists and is discoverable via `php artisan list`.
- Running command with valid input dispatches or executes
  `ProcessSchoolMessage`.
- Command path matches webhook argument contract.
- Clear CLI output is provided for success, queued, and error states.
- Feature tests validate both dispatch behavior and parity with webhook flow.

## Status

done

## Commits

- 86be79122cc03bf93976edca31b2472c49bbd40c

