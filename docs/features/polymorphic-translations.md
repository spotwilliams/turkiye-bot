# Title

Polymorphic Translations Table

## Description

Move translations off `messages` columns (`translation_en`, `translation_es`)
into a dedicated `translations` table that can attach to any model
(messages, tasks, reminders, future entities). Unlock arbitrary languages
beyond English and Spanish, and enable on-demand translation of any
internal text.

### Problem

Translations live as fixed columns on `messages` (`translation_en`,
`translation_es`). Adding a new language requires a migration. Other
models (tasks, reminders) have no translation slot at all, so user-facing
text on those entities cannot be localized without schema churn.

### Goal

- Single `translations` table reusable across models via polymorphic relation.
- Support arbitrary locales without migrations.
- Migrate existing `messages.translation_en` / `messages.translation_es`
  data into the new table.
- Keep current message-translation behavior intact for users.
- Lay groundwork for on-demand translation of task/reminder text.

### Proposed Behavior

- New `translations` table holds one row per (translatable, field, locale).
- `Message`, `Task`, `Reminder` models gain `HasMany` translations relation
  via polymorphic `morphMany`.
- `ProcessSchoolMessage` job writes English + Spanish translations into
  `translations` table instead of `messages` columns.
- Reading translation goes through helper:
  - `$message->translation('en')` returns English text or null,
  - `$message->translations` returns all locales.
- Existing user-facing flows (Telegram confirmation, digest) read via the
  helper — output unchanged for end users.
- Future on-demand flow (out of scope here, but unblocked): translate any
  task/reminder text into a requested locale and persist it.

### Data/Schema Requirements

- Create `translations` table:
  - `id`
  - `translatable_type` (string)
  - `translatable_id` (unsignedBigInt)
  - `field` (string) — e.g. `body`, `description`, `summary`
  - `locale` (string, e.g. `en`, `es`, `tr`, `fr`)
  - `text` (text)
  - `source` (string, nullable) — `ai`, `manual`, `import`
  - `timestamps`
- Indexes:
  - composite index `(translatable_type, translatable_id)`
  - unique index `(translatable_type, translatable_id, field, locale)`
- Migration drops `translation_en` and `translation_es` from `messages`
  after backfill.
- Backfill migration:
  - copy `translation_en` -> translations row (`field=body`, `locale=en`),
  - copy `translation_es` -> translations row (`field=body`, `locale=es`),
  - run before column drop.

### UX Requirements

- No visible change to Telegram confirmation message or daily digest;
  English + Spanish blocks render same as today.
- Internal logs/debug may surface `source` and `locale` fields.

### Parity / Integration Requirements

- Reuse `App\Jobs\ProcessSchoolMessage` — only swap persistence path from
  message columns to translations relation.
- `App\Services\TelegramService::sendProcessedConfirmation()` reads via
  `$message->translation('en')` / `translation('es')` helpers; signature
  unchanged.
- `App\Models\Message` exposes `translations()` morphMany and
  `translation(string $locale, string $field = 'body')` accessor.
- Apply same `HasTranslations` trait (or concern) to `Task` and `Reminder`
  even though no writers populate them yet — keeps API uniform.
- Wayfinder / Inertia dashboard: any view that read `translation_en` /
  `translation_es` switches to the helper.

### AI / Parsing Strategy

- `SchoolMessageProcessor` agent schema unchanged (still returns
  `translation_en`, `translation_es` keys in structured output).
- Job maps agent output -> translations rows:
  - `translation_en` -> `(message, body, en, ai)`,
  - `translation_es` -> `(message, body, es, ai)`.
- Future on-demand translator agent can write rows with `source=ai` and
  any `locale`.

### Idempotency & Concurrency

- Unique index `(translatable_type, translatable_id, field, locale)`
  prevents duplicate rows under concurrent writes.
- Writer uses `updateOrCreate` keyed on the unique tuple — re-running the
  job replaces text, not duplicates it.

### Acceptance Criteria

- `translations` table exists with polymorphic columns and unique index.
- New messages persist English + Spanish text into `translations`, not
  `messages` columns.
- `messages.translation_en` and `messages.translation_es` columns removed
  after data backfill.
- All existing user-facing surfaces still render English + Spanish text
  correctly via the helper.
- `Task` and `Reminder` models expose translations relation (even if
  unused initially).
- Feature tests cover:
  - job persists `en` + `es` rows for new message,
  - re-running job updates existing rows (no duplicates),
  - confirmation message renders text from translations table,
  - backfill migration moves all legacy column data into translations,
  - polymorphic isolation: a message translation does not leak to a task
    of same id,
  - unique-index race guard on duplicate write.

## Status

pending

## Commits

- (pending)

## Implementation Notes

<Fill after implementation: trait location, helper signatures, migration
order, any deferred work for on-demand translator feature.>
