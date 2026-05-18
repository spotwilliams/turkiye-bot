# Title

<Short feature name>

## Description

<1-2 sentence summary of what feature do and why exist>

### Problem

<Pain point today. What user cannot do, or what break, or what slow.>

### Goal

<What feature must achieve. Bullet list of concrete outcomes if helpful.>

### Proposed Behavior

- <User-visible behavior step by step>
- <Inputs, commands, triggers>
- <Validation rules>
- <Outputs, responses, side effects>

### Data/Schema Requirements

- <New columns, indexes, migrations>
- <Relations, eager loads>
- <Timezone, scoping rules>

### UX Requirements

- <Telegram/CLI/UI message formats>
- <Success message example>
- <Error message example>
- <Empty state>

### Parity / Integration Requirements

- <Reuse existing job/service/agent — name it>
- <Keep contract identical with X path>
- <Backward compatibility constraints>

### AI / Parsing Strategy (if applicable)

- <Agent or parser component name>
- <Input -> normalized output contract>
- <Fallback when parse fail>

### Idempotency & Concurrency (if applicable)

- <Race conditions to guard>
- <DB unique index or lock strategy>
- <Retry / dedupe behavior>

### Acceptance Criteria

- <Observable behavior 1>
- <Observable behavior 2>
- Feature tests cover:
  - <case 1>
  - <case 2>
  - <chat / tenant isolation>
  - <error path>

## Status

pending

## Commits

- (pending)

## Implementation Notes

<Fill after implementation. Where logic live, helper names, index names, gotchas.>
