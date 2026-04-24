# Title

Multi-Tier LLM Provider Strategy (Ollama → Gemini → Anthropic)

## Description

Refactor the AI configuration and the `SchoolMessageProcessor` agent so that the
project can run end-to-end against free providers during development and
staging, and only use paid Anthropic Claude as a final, optional production
step. The switch between providers must be fully `.env`-driven — no code
changes required to move between tiers.

### Problem

The current setup hardcodes Anthropic as the agent's provider via PHP
attributes (`#[Provider(Lab::Anthropic)]`, `#[Model('claude-sonnet-4-5')]`),
and `config/ai.php` defaults text generation to OpenAI. Both choices force
spending money (or having credentials for paid providers) just to exercise
the pipeline locally, which contradicts the provider strategy documented in
`docs/LLM_PROVIDERS.md`.

We want:

1. Local development to run entirely on **Ollama** (free, no internet, no
   limits).
2. Quality validation / staging to run on **Google Gemini Free Tier** (free,
   ~1,500 req/day, strong multilingual quality).
3. Production to optionally run on **Anthropic Claude** — and to be able to
   stay on Gemini free tier if quality is good enough.

### Goal

Provide a single, `.env`-driven configuration path that lets any environment
select its text provider and model without touching PHP, while keeping the
agent's contract (instructions + structured schema) unchanged across
providers.

### Proposed Behavior

#### 1. `config/ai.php`

- Set default text provider and model from env with Ollama-friendly fallbacks:
  ```php
  'defaults' => [
      'text' => [
          'provider' => env('AI_DEFAULT_TEXT_PROVIDER', 'ollama'),
          'model'    => env('AI_DEFAULT_TEXT_MODEL', 'gemma3:12b'),
      ],
  ],
  ```
- Keep all existing `providers` entries (anthropic, gemini, groq, mistral,
  ollama, openai, …) so the SDK can resolve any of them when selected.
- Make `ollama.url` default friendly for both native and Sail setups
  (`http://localhost:11434`, overridable with `OLLAMA_URL` to
  `http://host.docker.internal:11434` inside Docker).

#### 2. `App\Ai\Agents\SchoolMessageProcessor`

- Remove hard-coded provider/model attributes:
  - Drop `#[Provider(Lab::Anthropic)]`, `#[Model('claude-sonnet-4-5')]`, and
    `#[UseCheapestModel]`.
  - The agent must rely on the SDK's default text provider/model resolution so
    that whatever `config/ai.php` (driven by `.env`) selects is used.
- Keep instructions and structured schema unchanged — they must work
  identically across Ollama, Gemini and Anthropic.
- Allow per-call overrides via the SDK's `prompt(provider: ..., model: ...)`
  signature for ad-hoc quality checks, without changing defaults.

#### 3. `.env` Templates

- Document three `.env` profiles in the feature (and update `.env.example`):
  - **Local / default**:
    ```env
    AI_DEFAULT_TEXT_PROVIDER=ollama
    AI_DEFAULT_TEXT_MODEL=gemma3:12b
    OLLAMA_URL=http://host.docker.internal:11434
    OLLAMA_API_KEY=ollama
    ```
  - **Staging / Gemini free tier**:
    ```env
    AI_DEFAULT_TEXT_PROVIDER=gemini
    AI_DEFAULT_TEXT_MODEL=gemini-2.5-flash
    GEMINI_API_KEY=xxxx
    ```
  - **Production / Anthropic (last step, optional)**:
    ```env
    AI_DEFAULT_TEXT_PROVIDER=anthropic
    AI_DEFAULT_TEXT_MODEL=claude-sonnet-4-20250514
    ANTHROPIC_API_KEY=xxxx
    ```
- `ANTHROPIC_API_KEY` becomes optional. The app must boot and process
  messages without it as long as another provider is selected.

#### 4. Failover (optional, later)

- Keep the door open for automatic failover across providers using the SDK's
  multi-provider support, e.g. Ollama → Gemini at dev time, or Anthropic →
  Gemini in production. This feature does not implement failover itself
  (see `ai-processing-failover.md`), but the configuration must not block it.

### Rollout Plan

Implement and validate in three phases, matching the spend posture:

1. **Phase 1 — Ollama (local, free):**
   - Remove provider attributes from the agent.
   - Flip `config/ai.php` defaults to Ollama + `gemma3:12b`.
   - Update `.env.example`.
   - Verify the full pipeline (webhook → job → agent → tasks/reminders)
     against a running local Ollama with `gemma3:12b`.
2. **Phase 2 — Gemini Free Tier (staging):**
   - Document staging `.env` profile.
   - Run the 5 Turkish sample messages from `CLAUDE.md` through the bot with
     `AI_DEFAULT_TEXT_PROVIDER=gemini`.
   - Validate translation quality, structured-output reliability, and
     reminder scheduling.
3. **Phase 3 — Anthropic (production, optional last step):**
   - Document production `.env` profile.
   - Only enable if Gemini quality is insufficient.
   - Budget target stays under a few dollars/month given low volume.

### Testing Strategy

- Unit/feature tests must stay provider-agnostic: they already use
  `SchoolMessageProcessor::fake(...)` which bypasses any real provider.
- Add a configuration test asserting that:
  - The default text provider/model comes from env (with Ollama fallbacks).
  - The agent class declares no hard-coded `#[Provider]` or `#[Model]`
    attributes so its provider is resolved dynamically.
- Manual smoke test checklist (per phase):
  - Ollama: `curl http://localhost:11434/api/tags` lists `gemma3:12b`, then
    send one Turkish sample via the bot and verify tasks + reminders.
  - Gemini: swap `.env`, re-run the same Turkish sample.
  - Anthropic (if used): swap `.env`, re-run the same sample.

### Non-Goals

- Implementing automatic provider failover (tracked in
  `ai-processing-failover.md`).
- Introducing any new AI SDK abstractions beyond what already exists.
- Changing the agent's instructions or structured schema.

### Acceptance Criteria

- `config/ai.php` resolves the text provider and model from env with
  Ollama-friendly defaults.
- `App\Ai\Agents\SchoolMessageProcessor` contains no provider- or model-
  locking PHP attributes.
- The app runs end-to-end against Ollama locally with no Anthropic/OpenAI
  credentials present.
- Swapping to Gemini requires only `.env` changes (provider, model, API key).
- Swapping to Anthropic requires only `.env` changes.
- `.env.example` documents the three profiles (local, staging, production).
- Existing agent and job tests continue to pass with no real provider calls.
- Documentation in `docs/LLM_PROVIDERS.md` remains the source of truth and is
  consistent with the new defaults (update if needed).

## Status

done

## Commits

- 4a642b59827ce87da19f9cf0456828e0fab41911
