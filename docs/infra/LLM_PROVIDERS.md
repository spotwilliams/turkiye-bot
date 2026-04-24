# LLM Providers — Development & Production Setup

## Overview

This project uses the **Laravel AI SDK** (`laravel/ai`) for all LLM interactions.
The SDK is provider-agnostic: all AI logic lives in Agent classes, and the provider
is configured via `config/ai.php` and `.env`. Switching between dev (free) and
production (paid) providers requires zero code changes — only `.env` updates.

---

## Provider Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│                     DEVELOPMENT                                  │
│                                                                  │
│  Layer 1: Unit Tests → Agent::fake()                             │
│     No LLM calls at all. Hardcoded responses.                    │
│     Use for: CI/CD, pipeline logic, database tests.              │
│                                                                  │
│  Layer 2: Local Dev → Ollama                                     │
│     Free, no limits, no internet needed.                         │
│     Use for: prompt iteration, pipeline integration.             │
│     Model: gemma3:12b (best multilingual) or llama3.1:8b         │
│                                                                  │
│  Layer 3: Quality Validation → Google Gemini Free Tier           │
│     Free, 1,500 req/day, strong multilingual.                    │
│     Use for: validating Turkish translation quality,             │
│     structured output reliability, pre-production QA.            │
│                                                                  │
│  Layer 4: Speed Testing → Groq Free Tier                         │
│     Free, ~14,400 req/day, 300+ tok/s.                           │
│     Use for: load testing, latency benchmarks.                   │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│                     PRODUCTION                                   │
│                                                                  │
│  Primary: Anthropic Claude (claude-sonnet-4-20250514)            │
│     Paid. Best structured output + Turkish comprehension.        │
│     Failover: Google Gemini (paid tier)                          │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Provider Details

### 1. Ollama (Local) — Primary Dev Provider

**What:** Run open-source LLMs locally on your machine. No API key, no cost, no rate limits.

**Laravel AI SDK Support:** Native. Ollama is a built-in provider in the SDK.

**Setup:** This part was done by the human, just verify of Ollama is running 

```bash
# Install Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Pull recommended models
ollama pull gemma3:12b          # Best for multilingual (Turkish). ~7GB
ollama pull llama3.1:8b         # Good general purpose. ~4.7GB
ollama pull qwen3:8b            # Alternative, strong at structured output

# Verify it's running
curl http://localhost:11434/api/tags
```

**If using Sail (Docker):** Ollama runs on the HOST machine, not inside Docker.
The Sail containers need to reach the host. Use `host.docker.internal`:

```env
# .env for Sail + Ollama on host
OLLAMA_API_KEY=ollama
OLLAMA_URL=http://host.docker.internal:11434
```

**Alternatively**, add Ollama as a Sail service in `docker-compose.yml`:

```yaml
ollama:
    image: ollama/ollama
    ports:
        - "11434:11434"
    volumes:
        - ollama-data:/root/.ollama
    deploy:
        resources:
            reservations:
                devices:
                    - driver: nvidia
                      count: all
                      capabilities: [gpu]

volumes:
    ollama-data:
```

If using the Docker approach:
```env
OLLAMA_API_KEY=ollama
OLLAMA_URL=http://ollama:11434
```

**config/ai.php:**

```php
'ollama' => [
    'driver' => 'ollama',
    'key' => env('OLLAMA_API_KEY', 'ollama'),
    'url' => env('OLLAMA_URL', 'http://localhost:11434'),
],
```

**Recommended Models by Task:**

| Task                         | Model          | Why                                    |
|------------------------------|----------------|----------------------------------------|
| Turkish translation          | gemma3:12b     | Google model, strong multilingual      |
| Structured output extraction | llama3.1:8b    | Good at following JSON schemas         |
| General dev/testing          | llama3.1:8b    | Fast, reliable, low RAM                |
| Better quality (16GB+ RAM)   | llama3.3:70b   | Near cloud quality, needs good hardware|

**Limitations:**
- Smaller models (8B) produce lower quality Turkish translations
- Structured output may have occasional formatting issues
- Speed depends on your hardware (GPU recommended but not required)
- Good enough to test the pipeline, not the final output quality

---

### 2. Google Gemini (Free Tier) — Quality Validation Provider

**What:** Google's Gemini 2.5 Flash, free for prototyping. Best free cloud model available.

**Laravel AI SDK Support:** Native. Gemini is a built-in provider.

**Get API Key:** https://aistudio.google.com — no credit card required.

**Free Tier Limits:**
- ~1,500 requests/day
- 1 million token context window
- Multimodal (images, PDFs) included
- Data may be used for training (opt out in settings)

**Setup:**

```env
GEMINI_API_KEY=your_free_key_here
```

**config/ai.php:**

```php
'gemini' => [
    'driver' => 'gemini',
    'key' => env('GEMINI_API_KEY'),
],
```

**When to use:** After building the pipeline with Ollama, switch to Gemini to validate
that Turkish translations and task extraction work correctly with a production-quality model.

**Limitations:**
- Free tier is for prototyping only (Google ToS)
- Not available in EU/UK/Switzerland on free tier (check current status)
- No SLA, no uptime guarantee
- Rate limits can change without notice

---

### 3. Groq (Free Tier) — Speed Testing Provider

**What:** Custom LPU hardware running open-source models at extreme speed (300+ tok/s).

**Laravel AI SDK Support:** Native. Groq is a built-in provider.

**Get API Key:** https://console.groq.com — no credit card required.

**Free Tier Limits:**
- ~14,400 requests/day (varies by model)
- 30 requests/minute
- Llama 3.3 70B, Mixtral, Gemma available

**Setup:**

```env
GROQ_API_KEY=your_free_key_here
```

**config/ai.php:**

```php
'groq' => [
    'driver' => 'groq',
    'key' => env('GROQ_API_KEY'),
],
```

**When to use:** When you need fast iteration cycles or want to test how the system
behaves under rapid sequential requests.

**Limitations:**
- Rate limits vary by model (Llama 4 Maverick limited to 500 req/day)
- Structured output support varies by model
- Not all models support tool use

---

### 4. Mistral (Free Tier) — Alternative Cloud Provider

**What:** ~1 billion tokens/month free across all Mistral models.

**Laravel AI SDK Support:** Native. Mistral is a built-in provider.

**Get API Key:** https://console.mistral.ai — no credit card required.

**Free Tier Limits:**
- ~1B tokens/month
- 2 requests/minute (low concurrency)
- Access to all models including Mistral Large and Codestral

**Setup:**

```env
MISTRAL_API_KEY=your_free_key_here
```

**config/ai.php:**

```php
'mistral' => [
    'driver' => 'mistral',
    'key' => env('MISTRAL_API_KEY'),
],
```

**When to use:** When Gemini free tier is unavailable in your region, or you need
access to a strong model with a very generous monthly token budget.

**Limitations:**
- Very low concurrency (2 RPM) — not suitable for rapid testing
- Structured output quality varies

---

### 5. Anthropic Claude (Production) — Primary Production Provider

**What:** Claude Sonnet 4, best-in-class for structured output and multilingual tasks.

**Laravel AI SDK Support:** Native. Anthropic is a built-in provider.

**Get API Key:** https://console.anthropic.com — requires credit card.

**Pricing:** Pay-as-you-go. For this project's volume (~10-20 messages/week),
expect less than $1/month.

**Setup:**

```env
ANTHROPIC_API_KEY=your_key_here
```

**config/ai.php:**

```php
'anthropic' => [
    'driver' => 'anthropic',
    'key' => env('ANTHROPIC_API_KEY'),
],
```

**Why this is the production choice:**
- Most reliable structured output (JSON schemas)
- Excellent Turkish comprehension and translation
- Consistent, predictable quality
- Strong at following complex instructions (reminder scheduling logic)

---

## Switching Providers

The entire switch happens in `.env`. No code changes needed.

### .env.dev (local development)

```env
AI_DEFAULT_TEXT_PROVIDER=ollama
AI_DEFAULT_TEXT_MODEL=gemma3:12b
OLLAMA_API_KEY=ollama
OLLAMA_URL=http://host.docker.internal:11434
```

### .env.staging (quality testing)

```env
AI_DEFAULT_TEXT_PROVIDER=gemini
AI_DEFAULT_TEXT_MODEL=gemini-2.5-flash
GEMINI_API_KEY=your_free_key
```

### .env.production

```env
AI_DEFAULT_TEXT_PROVIDER=anthropic
AI_DEFAULT_TEXT_MODEL=claude-sonnet-4-20250514
ANTHROPIC_API_KEY=your_paid_key
```

### Per-request override (when needed)

You can also override the provider at prompt time without changing `.env`:

```php
use App\Ai\Agents\SchoolMessageProcessor;
use Laravel\Ai\Enums\Lab;

// Use Anthropic for this specific call, even if default is Ollama
$response = SchoolMessageProcessor::make()->prompt(
    $turkishMessage,
    provider: Lab::Anthropic,
    model: 'claude-sonnet-4-20250514',
);
```

---

## Testing Without Any LLM

For unit tests and CI/CD, use `Agent::fake()` to avoid LLM calls entirely:

```php
use App\Ai\Agents\SchoolMessageProcessor;
use Laravel\Ai\Facades\Agent;

Agent::fake([
    SchoolMessageProcessor::class => [
        'translation_en' => 'Please bring 350 TL by Tuesday.',
        'translation_es' => 'Traigan 350 TL antes del martes.',
        'summary' => 'Money needed for trip.',
        'tasks' => [[
            'description' => 'Bring 350 TL for trip',
            'category' => 'money',
            'due_date' => '2026-01-14',
            'due_time' => null,
            'amount' => 350,
            'currency' => 'TRY',
            'reminders' => [
                [
                    'scheduled_at' => '2026-01-13T20:00',
                    'message' => 'Prepare 350 TL for tomorrow',
                    'type' => 'preparation',
                ],
                [
                    'scheduled_at' => '2026-01-14T07:30',
                    'message' => 'Put 350 TL in backpack',
                    'type' => 'action',
                ],
            ],
        ]],
    ],
]);

// This makes zero API calls
$response = SchoolMessageProcessor::make()->prompt('Any text here');
```

---

## Failover Configuration

The Laravel AI SDK supports automatic failover. If the primary provider fails,
it falls back to the next one:

```php
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Enums\Lab;

#[Provider(Lab::Anthropic, Lab::Gemini)]
class SchoolMessageProcessor implements Agent, HasStructuredOutput
{
    // If Anthropic fails, automatically retries with Gemini
}
```

---

## config/ai.php — Full Development Configuration

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Providers
    |--------------------------------------------------------------------------
    |
    | Switch these via .env to change providers without touching code.
    | Development: ollama or gemini
    | Production: anthropic
    |
    */

    'defaults' => [
        'text' => [
            'provider' => env('AI_DEFAULT_TEXT_PROVIDER', 'anthropic'),
            'model' => env('AI_DEFAULT_TEXT_MODEL', 'claude-sonnet-4-20250514'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Configure all providers you might use. Only the active one (set above)
    | needs valid credentials. Others can have empty keys safely.
    |
    */

    'providers' => [

        // PRODUCTION: Anthropic Claude
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
        ],

        // DEV: Local Ollama
        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', 'ollama'),
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],

        // DEV/STAGING: Google Gemini (free tier)
        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
        ],

        // DEV: Groq (free tier, fast inference)
        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
        ],

        // DEV: Mistral (free tier, high token budget)
        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
        ],
    ],
];
```

---

## Quick Reference: Free Tier Comparison

| Provider       | Rate Limit         | Best Model (Free)     | Structured Output | Turkish Quality | Card Required |
|----------------|--------------------|-----------------------|-------------------|-----------------|---------------|
| **Ollama**     | Unlimited          | gemma3:12b            | ⚠️ Varies         | ⭐⭐⭐           | No            |
| **Gemini**     | ~1,500 req/day     | Gemini 2.5 Flash      | ✅ Good           | ⭐⭐⭐⭐          | No            |
| **Groq**       | ~14,400 req/day    | Llama 3.3 70B         | ⚠️ Varies         | ⭐⭐⭐           | No            |
| **Mistral**    | ~1B tokens/month   | Mistral Large         | ✅ Good           | ⭐⭐⭐           | No            |
| **Claude** 💰  | Pay-as-you-go      | Claude Sonnet 4       | ✅ Excellent      | ⭐⭐⭐⭐⭐         | Yes           |

---

## Agentic Instructions

The following instructions are for AI coding agents (Claude Code, Cursor, Copilot, etc.)
working on this project. Follow these rules when implementing or modifying LLM-related code.

### Rule 1: Never Hardcode a Provider

All LLM interactions MUST go through the Laravel AI SDK Agent classes.
Never make raw HTTP calls to any LLM API. Never import provider-specific SDKs.

```php
// ❌ WRONG: Raw HTTP call
Http::post('https://api.anthropic.com/v1/messages', [...]);

// ❌ WRONG: Provider-specific SDK
$client = new \Anthropic\Client($apiKey);

// ✅ CORRECT: Laravel AI SDK Agent
$response = SchoolMessageProcessor::make()->prompt($message);
```

### Rule 2: Provider Configuration Lives in .env Only

When asked to "switch providers" or "use a different model", only modify `.env` values.
Never change agent class code to accommodate a specific provider.

```php
// ❌ WRONG: Provider logic in agent
if (app()->environment('local')) {
    $provider = 'ollama';
} else {
    $provider = 'anthropic';
}

// ✅ CORRECT: .env handles this
// AI_DEFAULT_TEXT_PROVIDER=ollama     (in .env.dev)
// AI_DEFAULT_TEXT_PROVIDER=anthropic  (in .env.production)
```

### Rule 3: Always Use Structured Output

Every agent in this project MUST implement `HasStructuredOutput`.
Never parse free-text LLM responses with regex or string manipulation.

```php
// ❌ WRONG: Parsing free text
$text = $agent->prompt($message);
preg_match('/due date: (.+)/', $text, $matches);

// ✅ CORRECT: Structured output via schema
$response = SchoolMessageProcessor::make()->prompt($message);
$dueDate = $response['tasks'][0]['due_date']; // Guaranteed by schema
```

### Rule 4: Use Agent::fake() in Tests

Never make real LLM calls in automated tests. Always use `Agent::fake()`.

```php
// ❌ WRONG: Real API call in test
$response = SchoolMessageProcessor::make()->prompt('Test message');

// ✅ CORRECT: Fake response
Agent::fake([
    SchoolMessageProcessor::class => [
        'translation_en' => 'Test translation',
        'translation_es' => 'Traducción de prueba',
        'summary' => 'Test summary',
        'tasks' => [],
    ],
]);
$response = SchoolMessageProcessor::make()->prompt('Test message');
```

### Rule 5: Ollama Sail Networking

When working with Sail (Docker), Ollama runs on the host machine.
Containers must use `host.docker.internal` to reach it:

```env
# Inside Sail containers → reaching host Ollama
OLLAMA_URL=http://host.docker.internal:11434

# NOT localhost (that's the container itself)
# OLLAMA_URL=http://localhost:11434  ← WRONG inside Docker
```

If Ollama is added as a Docker service in `docker-compose.yml`,
use the service name instead:

```env
OLLAMA_URL=http://ollama:11434
```

### Rule 6: Model Pulling for Ollama

Before using Ollama, the model must be pulled. When setting up the dev environment
or encountering Ollama connection errors, run:

```bash
# On the HOST machine (not inside Sail)
ollama pull gemma3:12b

# Or if Ollama is a Docker service
docker exec -it school-reminder-ollama-1 ollama pull gemma3:12b
```

### Rule 7: Per-Request Provider Override

If a specific feature needs a particular provider (e.g., production-quality translation
check during development), use the prompt-level override:

```php
use Laravel\Ai\Enums\Lab;

$response = SchoolMessageProcessor::make()->prompt(
    $message,
    provider: Lab::Anthropic,
    model: 'claude-sonnet-4-20250514',
);
```

This does NOT change the default provider. It's a one-off override.

### Rule 8: Error Handling Across Providers

Different providers fail differently. Always wrap prompts in try/catch
and provide meaningful feedback. The job's retry mechanism handles transient failures.

```php
try {
    $response = SchoolMessageProcessor::make()->prompt($message);
} catch (\Laravel\Ai\Exceptions\ProviderException $e) {
    Log::error('LLM provider error', [
        'provider' => config('ai.defaults.text.provider'),
        'error' => $e->getMessage(),
    ]);
    // Let the queue retry (job has $tries = 3)
    throw $e;
}
```

### Rule 9: Adding a New Provider

To add a new provider (e.g., DeepSeek, xAI):

1. Add credentials to `.env`:
   ```env
   DEEPSEEK_API_KEY=your_key_here
   ```

2. Add provider config to `config/ai.php` under `providers`:
   ```php
   'deepseek' => [
       'driver' => 'deepseek',
       'key' => env('DEEPSEEK_API_KEY'),
   ],
   ```

3. Set it as default to test:
   ```env
   AI_DEFAULT_TEXT_PROVIDER=deepseek
   ```

4. Run the test suite to verify structured output works correctly.

No agent code should change.

### Rule 10: Context for SchoolMessageProcessor Agent

When modifying the `SchoolMessageProcessor` agent, keep in mind:

- **Input:** Raw Turkish text pasted from school WhatsApp messages
- **Output:** Structured JSON with translations (EN + ES), summary, and tasks with reminders
- **The agent must work identically across all providers** — instructions should not
  reference provider-specific features
- **Date context is critical** — the agent receives `currentDate` and `dayOfWeek`
  to calculate due dates from relative references like "next Friday" or "this Wednesday"
- **Reminder scheduling logic is in the prompt** — the agent calculates reminder
  datetimes based on category rules (money, item, homework, event)
- **Test with Turkish text samples** from the CLAUDE.md file when validating changes

### Rule 11: Development Workflow

When starting work on this project:

1. Verify Ollama is running: `curl http://localhost:11434/api/tags`
2. Verify the model is pulled: check for `gemma3:12b` in the response
3. Verify `.env` has `AI_DEFAULT_TEXT_PROVIDER=ollama`
4. Run `./vendor/bin/sail artisan queue:work` for job processing
5. Run tests with `./vendor/bin/sail artisan test` (uses Agent::fake, no LLM needed)

When validating quality before a deploy:

1. Switch to `AI_DEFAULT_TEXT_PROVIDER=gemini` in `.env`
2. Send the 5 test messages from CLAUDE.md through the bot
3. Verify translations, task extraction, and reminder scheduling
4. Switch back to `AI_DEFAULT_TEXT_PROVIDER=anthropic` for production