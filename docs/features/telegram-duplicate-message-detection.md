# Title

Duplicate Message Detection

## Description

Prevent processing the same school message more than once by adding duplicate
detection before running the AI pipeline. Detection is based on the normalized
text content alone — no chat, family, or sender scoping.

### Problem

Users can accidentally resend the same message (copy/paste twice, Telegram
retry, or command mistakes). Parents in the same household often forward the
same teacher note to the bot from different accounts. Today this can create
duplicate `messages`, duplicate tasks/reminders, and redundant bot replies.

### Goal

Detect duplicates early and make processing idempotent, so any repeated
submission of the same text — regardless of who sent it or through which
entry point (webhook, CLI, future API) — reuses the existing record instead
of producing a new one.

### Candidate Approaches

#### Option A — Exact Raw Text Hash (Simple / Fast)

- Store `sha256(original_text)` as `raw_text_hash`.
- Compare by `raw_text_hash` alone.
- Pros:
  - easiest to implement,
  - deterministic and fast,
  - cheap DB index lookups.
- Cons:
  - whitespace/punctuation changes bypass duplicate detection.

#### Option B — Normalized Text Hash (Recommended Baseline)

- Normalize text before hashing, for example:
  - trim,
  - collapse repeated whitespace,
  - lowercase,
  - optional punctuation normalization.
- Store `normalized_text_hash`.
- Compare by `normalized_text_hash` alone.
- Pros:
  - still simple and deterministic,
  - catches most accidental duplicates (whitespace/case variants),
  - minimal performance cost.
- Cons:
  - not semantic; paraphrased text will still be treated as new.

#### Option C — Similarity / Semantic Matching (Advanced)

- Compare new message against recent messages using embeddings or fuzzy scoring.
- Pros:
  - can catch paraphrases and near-duplicates.
- Cons:
  - higher complexity and latency,
  - false positives/negatives risk,
  - harder to reason about operationally.

### Recommendation

Implement **Option B** first (normalized hash + a global unique DB index on
the hash), then revisit Option C only if duplicate noise remains high.

### Scope Decision: Text-Only

We intentionally drop any chat/family/sender scope from the dedupe key. The
same school note is authored once by a teacher and delivered to many parents;
whoever submits it first "wins" and the record is reused for everyone else.
Rationale:

- Keeps the schema trivial (single `normalized_text_hash` column, single
  unique index).
- Matches how the content actually behaves: identical text = identical work.
- Avoids needing a `family_id` or household registration step just to dedupe.

If later we need per-chat reporting of duplicates (e.g., "you submitted this
twice"), we can track that with a separate submissions/audit table without
changing the dedupe primitive.

### Proposed Behavior

- On incoming webhook message (and equivalent CLI command):
  1. compute normalized text,
  2. compute hash (`normalized_text_hash`),
  3. look up existing row with the same `normalized_text_hash`.
- If duplicate exists:
  - do not dispatch `ProcessSchoolMessage`,
  - return a short Telegram ack like:
    - `ℹ️ I already received this message and it's being tracked.`
  - reference the existing message id.
- If no duplicate:
  - persist and continue normal processing path.

### Data/Schema Requirements

- Add columns to `messages`:
  - `normalized_text` (optional, for debugging),
  - `normalized_text_hash` (required for lookup).
- Add a unique index on `normalized_text_hash` (global scope).
- Collision strategy:
  - SHA-256 collisions are negligible; treat as unique safe enough.

### Idempotency & Concurrency

- Protect against race conditions where the same text arrives from two
  sources within milliseconds:
  - rely on the DB unique index as the final guard.
- Application-level pre-check is best-effort only; it short-circuits the
  common case and avoids a pointless agent call.
- On unique-constraint conflict:
  - treat as a duplicate,
  - ack gracefully and return the existing row.

### Telegram / CLI UX Requirements

- Duplicate submission:
  - short, non-error acknowledgment,
  - reassure the user nothing was lost,
  - include existing message id.
- First submission:
  - normal "received/processing" response.
- Cross-chat duplicate:
  - same acknowledgment even if submitted from a different Telegram chat,
    because the text itself is the dedupe key.

### Acceptance Criteria

- Duplicate messages (identical normalized text) do not create duplicate
  records, regardless of which chat or entry point they came from.
- First unique message still follows the normal processing flow.
- Hash strategy catches common accidental duplicates (whitespace/case
  variants).
- System is safe under concurrent duplicate submissions (DB unique index
  enforces it).
- Feature tests cover:
  - exact duplicate submission to the webhook,
  - same text from a different chat (still a duplicate),
  - normalized duplicate variants (whitespace/case),
  - CLI `message:new` detecting a duplicate and rendering the existing record
    without calling the agent,
  - DB unique-constraint path (race guard).

## Status

done

## Commits

- (pending)

## Implementation Notes

- Normalization and hashing live on `App\Models\Message` as
  `normalizeText()` and `hashText()` static methods.
- Lookup helper is `Message::findByText(string $text)`; used by both
  `TelegramWebhookController` and the `message:new` command.
- DB unique index `messages_hash_unique` on `normalized_text_hash` is the
  final guard against races.
- `TelegramService::sendDuplicateAck()` handles the webhook ack; the CLI
  command surfaces the duplicate via a `warning()` prompt and re-renders the
  existing message with its tasks and reminders.
