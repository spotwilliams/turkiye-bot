# Title

Telegram Pending Tasks On Demand

## Description

Allow parents to request pending tasks directly in Telegram with `/pending` and
receive a clear, human-friendly digest grouped by source message summary.

### Problem

Users currently need a quick way to see everything still pending without waiting
for scheduled reminders or manually checking elsewhere. The response must be
scannable, actionable, and understandable under time pressure.

### Goal

Implement `/pending` so the bot returns all pending tasks for the current
Telegram chat in a format that is easy to follow, including:

1. English summary of the originating school message,
2. task reference id and description,
3. due date plus relative status (remaining time or delayed),
4. assignee (when a family member is assigned).

### Proposed Behavior

- When user sends `/pending`:
  - fetch all `pending` tasks for that `telegram_chat_id`,
  - include related `message` (for summary) and assignee metadata,
  - sort tasks by due date/time ascending (most urgent first).
- Group output by originating `message_id` (or summary block) so context is
  preserved.
- For each task line include:
  - `(task reference id) task description`
  - `Due date: dd/mm/yy` + status:
    - `x days/hours remaining` when future,
    - `delayed by x days/hours` when past due.
  - `Who: <name|both|unassigned>` when applicable.
- If there are no pending tasks:
  - send a concise success message (e.g., "No pending tasks. You're all set!").

### Message Format (Target)

- Summary in English of the originating message
  - `(123) Bring 350 TL for field trip`
    - `Due date: 26/04/26 (2 days remaining)`
    - `Who: father`
  - `(124) Bring red t-shirt`
    - `Due date: 26/04/26 (2 days remaining)`
    - `Who: both`

- Summary in English of another originating message
  - `(125) Math homework pages 45-48`
    - `Due date: 28/04/26 (4 days remaining)`
    - `Who: mother`

### Data/Query Requirements

- Query by `telegram_chat_id` and `status = pending`.
- Eager load `message` relation to avoid N+1 lookups for summaries.
- Include assignment source:
  - use task assignment field(s) if present,
  - resolve to family member name when linked.
- Compute relative due status using app timezone (`Europe/Istanbul` unless
  overridden per member/chat).

### Command Handling Requirements

- Extend Telegram command handling to recognize `/pending`.
- Keep backward compatibility with existing bot commands.
- Response should stay within Telegram message size limits; if needed:
  - chunk output into multiple messages,
  - or limit with "show more" follow-up strategy.

### Acceptance Criteria

- Sending `/pending` returns pending tasks for that chat only.
- Tasks are grouped by source message summary.
- Each task shows reference id, description, due date, relative status, assignee.
- Relative status correctly distinguishes remaining vs delayed.
- Empty state message is clear when no tasks exist.
- Feature tests cover:
  - no pending tasks,
  - mixed due/future tasks,
  - grouped output for multiple source messages,
  - assigned vs unassigned tasks,
  - chat isolation (one chat cannot see another chat's tasks).

## Status

done

## Commits

- 485358974b8f3d28bb58c352266ad6d11eb475f3

