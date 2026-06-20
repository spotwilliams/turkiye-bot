# Title

Web Action Surface — Deterministic Reminder Generator

## Problem Statement

Reminders have only ever been born from the AI agent: `SchoolMessageProcessor`
returns reminder rows in its structured output and `ProcessSchoolMessage`
persists them verbatim. But the web surface needs to **reschedule** and **edit**
tasks, which must regenerate reminders for a new due date or category — and
you cannot re-call the LLM for a pure date shift (slow, costs tokens, risks the
model drifting or re-translating). There is no deterministic way to produce the
correct reminders for a task today.

## Solution

Introduce a deterministic `GenerateTaskReminders` module that encodes the
category timing rules in PHP, and make it the **single source** of reminder
generation. The AI agent stops emitting reminders entirely; PHP builds them at
ingest *and* on every reschedule/edit. This removes the "rules in two places"
problem and guarantees ingest and reschedule produce identical reminder
patterns.

## User Stories

1. As a parent, I want reminders to be recreated correctly when I reschedule a
   task, so that I am nudged on the right days for the new due date.
2. As a parent, I want editing a task's category to update its reminders, so
   that a task reclassified from "item" to "homework" gets homework-style
   reminders.
3. As a developer, I want one place that defines reminder timing, so that
   ingest and reschedule never drift apart.
4. As an operator, I want rescheduling to be instant and free, so that it does
   not trigger an AI call or re-translation.
5. As a parent, I want already-sent reminders preserved when reminders are
   regenerated, so that history is not lost and I am not re-nudged for past
   events.
6. As a developer, I want the AI schema to be smaller, so that prompts are
   cheaper and the structured output is simpler to validate.

## Implementation Decisions

- **New module `GenerateTaskReminders`** (deep, deterministic). Input: a task
  (with category, due date, optional due time, amount, description). Output:
  the set of reminder rows per the category rules below. It only generates;
  callers decide persistence.
- **Category timing rules** (from CLAUDE.md reminder strategy):
  - **money:** 20:00 the evening before (`preparation`) + 07:30 morning of
    (`action`).
  - **item:** 20:00 the evening before (`preparation`) + 07:30 morning of
    (`action`).
  - **homework:** Saturday 10:00 (`preparation`) + Sunday 18:00 (`final`).
  - **event:** 20:00 two days before (`preparation`) + 20:00 evening before
    (`action`) + 07:30 morning of (`final`).
  - **other:** no reminders (or a single same-day nudge — default to none).
- **AI schema change:** `SchoolMessageProcessor::schema()` drops the
  `reminders` array from each task object. The agent returns translations,
  summary, and tasks (description/category/due_date/due_time/amount/currency)
  only. Instructions referencing reminder generation are removed.
- **Ingest refactor:** `ProcessSchoolMessage` creates each task, then calls
  `GenerateTaskReminders` to build and persist its reminders, instead of reading
  AI-provided reminder rows.
- **Regeneration contract (consumed by PRD 04):** rescheduling or editing a
  task's due date/category **wipes unsent reminders** (`sent = false`) for that
  task and regenerates from the generator. **Sent reminders are kept** as
  history.
- **Timezone:** all generated timestamps use the app timezone
  (`Europe/Istanbul`).

## Testing Decisions

A good test asserts the generated reminder set (count, scheduled timestamps,
types) for a given task — pure, deterministic, no AI.

- **`GenerateTaskReminders` (unit) — primary target:**
  - money task → 2 reminders at evening-before 20:00 and morning-of 07:30.
  - item task → 2 reminders, same anchors.
  - homework task → Saturday 10:00 + Sunday 18:00 relative to the due week.
  - event task → 3 reminders (2-days-before, evening-before, morning-of).
  - `other` category → no reminders.
  - timestamps are in `Europe/Istanbul`.
- **Ingest (feature):** processing a sample Turkish message persists tasks with
  PHP-generated reminders; the `SchoolMessageProcessor` `Agent::fake` fixtures
  are updated to no longer include reminders, and existing agent tests are
  adjusted to the smaller schema.
- Prior art: existing `SchoolMessageProcessor` `Agent::fake` tests; reminder
  persistence assertions in the current `ProcessSchoolMessage` tests.

## Out of Scope

- Reschedule/edit actions themselves (PRD 04) — this PRD only provides the
  generator and the regeneration contract.
- Reminder *delivery* (PRD 03).
- Natural-language date input.

## Further Notes

This is a net simplification of the AI surface: the agent's job shrinks to
translate + summarize + extract tasks. Update
`docs/features/school-message-processor-agent.md` to reflect the removed
`reminders` schema branch when this lands.
