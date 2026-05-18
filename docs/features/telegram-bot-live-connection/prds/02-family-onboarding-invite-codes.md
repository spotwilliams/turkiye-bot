# PRD — Family Onboarding via Invite Codes

**Status: done** (implemented on branch `develop`).

## Problem Statement

There is no way for a legitimate Telegram user to register with the bot.
Today, `FamilyMember` rows must be created manually in the database before a
user's messages will be accepted. This blocks every live test (including the
operator's first round-trip) and makes inviting a second parent later
impossible without a developer.

## Solution

Closed-by-default access with self-service onboarding. The operator
generates a single-use invite code from the CLI, sends it out-of-band, and
the recipient redeems it by sending `/start <code>` to the bot. Redemption
captures the recipient's Telegram `from.id` and `chat.id` and creates the
`FamilyMember` row with the name and role pre-baked into the code.

## User Stories

1. As the operator, I want to generate a single-use invite code from the
   CLI, so that I can onboard a family member without editing the database.
2. As the operator, I want to bake `name` and `role` into the code at
   generation time, so that the user does not have to answer interview
   questions in the bot.
3. As the operator, I want each code to be valid only once, so that a
   leaked code cannot be reused by someone else.
4. As the operator, I want optional expiry on a code, so that I can issue
   short-lived invites if I share them in a less secure channel.
5. As an invited family member, I want to redeem my code by sending
   `/start <code>`, so that the bot becomes usable for me without operator
   intervention.
6. As an invited family member, I want a clear welcome message after
   redeeming, so that I know what commands I can use next.
7. As an invited family member, I want re-sending `/start <code>` after
   already registering to do nothing destructive, so that idempotency
   protects me from mistakes.
8. As an attacker, I want failed redemption attempts to give me no
   information about which code condition failed, so that I cannot
   enumerate valid codes — wait, this is the operator's wish. Restated:
   as the operator, I want generic error responses for any invalid code
   (unknown, used, expired, malformed), so that an attacker cannot
   distinguish cases via probing.
9. As the operator, I want concurrent claims of the same code to result in
   at most one `FamilyMember` row, so that a race cannot create duplicates.

## Implementation Decisions

- **New table `family_invites`**
  - `id`, `code` (string 64, unique), `name`, `role`, `expires_at`
    (nullable timestamp), `used_at` (nullable timestamp),
    `used_by_family_member_id` (nullable foreign key to `family_members`,
    null-on-delete), timestamps.

- **Eloquent model `FamilyInvite`**
  - `isClaimable()` predicate: not used, not expired.
  - `claim(int $telegramUserId, int $telegramChatId)`: wraps the
    `FamilyMember` creation and the invite update in one DB transaction
    with `lockForUpdate` on the invite row.

- **Action `RedeemFamilyInvite`** (single-purpose, in `app/Actions/`)
  - Input: invite code, `from.id`, `chat.id`.
  - Output: either the new `FamilyMember`, or a generic failure result
    (no enumerated reasons leaked).
  - Treats already-registered `from.id` as a separate idempotent outcome
    (reply differs from the generic invalid case).

- **Artisan `family:invite`**
  - Options: `--name="..."`, `--role=father|mother`, optional
    `--expires-in-hours=24`.
  - Generates `Str::random(32)` code, persists, prints to operator.

- **Controller handling**
  - `/start <code>` short-circuits before the allowlist check (this is the
    only legitimate path for an unknown sender).
  - Malformed `/start` (no code or extra args) → usage help reply.
  - Reply messages distinguish: success (welcome), already-registered
    (idempotent), invalid (generic — covers unknown/used/expired/any
    other rejection).

## Testing Decisions

A good test calls the controller with crafted Telegram payloads and
asserts the outward effects: a `FamilyMember` row created with the
expected fields, the invite row marked used, the correct Telegram reply
sent. It does not poke at the action's internals.

Modules under test:

- `RedeemFamilyInvite` action: unit tests for valid/unused, used,
  unknown, expired, double-claim under concurrency (use
  `DB::transaction` + a parallel call via `Pest`'s concurrency or a
  simulated double-claim test).
- Controller integration tests for `/start <code>`: 6 cases (valid,
  used, unknown, malformed, expired, already-registered `from.id`).
- `family:invite` artisan: argument validation, row persistence, code
  uniqueness.

Prior art: existing `tests/Feature/Admin/*` tests; existing
`tests/Feature/` tests around `ProcessSchoolMessage`.

## Out of Scope

- Multi-parent shared family / `families` table (parent doc, future
  iteration).
- Self-service `/leave` or invite revocation (operator can mark invite
  used via tinker for now).
- Admin panel page listing invites (could be added in a later UI PRD).

## Further Notes

Invite codes are 32 characters of `Str::random` → ~190 bits of entropy.
Combined with the rate limiter (PRD 3), enumeration is not a realistic
attack.
