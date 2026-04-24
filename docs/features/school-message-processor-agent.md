# Title

SchoolMessageProcessor AI Agent

## Description

Implement the core AI agent that powers the bot: a Laravel AI SDK agent that
receives a Turkish school message and returns structured data (translations,
summary, extracted tasks, and smart reminders) so the rest of the system can
persist and schedule reminders deterministically.

### Problem

The bot's value depends on reliably turning free-form Turkish school
announcements into actionable, translated, scheduled tasks. Parsing this
manually is error-prone and producing unstructured LLM output makes downstream
persistence fragile. We need a dedicated agent with a strict structured-output
schema so every response is predictable and parseable.

### Goal

Provide a single agent class — `App\Ai\Agents\SchoolMessageProcessor` — that:

1. translates the original Turkish message into English and Spanish,
2. summarizes the school's intent in 1-2 sentences,
3. extracts every actionable task with category, due date/time, and amount,
4. generates category-specific reminders with scheduled timestamps,
5. guarantees a structured response that maps 1:1 to the `messages`, `tasks`,
   and `reminders` tables.

### Proposed Behavior

- Class implements `Laravel\Ai\Contracts\Agent` and
  `Laravel\Ai\Contracts\HasStructuredOutput`, uses the `Promptable` trait.
- Constructor accepts optional `currentDate` and `dayOfWeek` (defaults to
  `now()`), injected into the system prompt so the model can resolve relative
  dates like "Friday" or "weekend".
- `instructions()` returns the system prompt describing the assistant's role,
  the three required operations (translate / summarize / extract tasks), the
  reminder-generation rules per category, and the handling of informational
  messages.
- `schema(JsonSchema $schema)` defines a strict output shape:
  - `translation_en` (string, required)
  - `translation_es` (string, required)
  - `summary` (string, required)
  - `tasks` (array, required) of objects containing:
    - `description` (string, required)
    - `category` (enum: money, homework, item, event, other — required)
    - `due_date` (string `YYYY-MM-DD`, required)
    - `due_time` (string `HH:MM`, nullable)
    - `amount` (number, nullable)
    - `currency` (string, nullable, defaults to `TRY`)
    - `reminders` (array, required) of objects with:
      - `scheduled_at` (string `YYYY-MM-DDTHH:MM`, required)
      - `message` (string, required)
      - `type` (enum: preparation, action, final — required)
- Usage: `SchoolMessageProcessor::make()->prompt($turkishMessage)` returns an
  array-accessible structured response.

### Reminder Generation Rules

The agent must produce reminders following the project's reminder strategy:

- **Money**: 20:00 the evening before ("Prepare X TL") + 07:30 the morning of
  ("Put X TL in backpack").
- **Item**: 20:00 the evening before ("Find and prepare [item]") + 07:30 the
  morning of ("Pack [item] in backpack").
- **Homework**: Saturday 10:00 ("Start homework: [description]") +
  Sunday 18:00 ("Check homework is done").
- **Event**: 20:00 two days before + 20:00 the evening before + 07:30 the
  morning of the event.

If the message is purely informational, the agent must return an empty
`tasks` array while still providing translations and a summary.

### Configuration Requirements

- Provider: `anthropic` via `config/ai.php` default text provider.
- Model: Claude Sonnet (as configured in `config/ai.php`).
- `ANTHROPIC_API_KEY` set in environment.
- Agent created via `php artisan make:agent SchoolMessageProcessor --structured`.

### Integration Points

- `App\Jobs\ProcessSchoolMessage` invokes
  `SchoolMessageProcessor::make()->prompt($turkishText)` and persists the
  result into `messages`, `tasks`, and `reminders` within a DB transaction.
- `raw_llm_response` on `messages` stores the full structured response for
  auditability.
- The AI processing failover feature (see `ai-processing-failover.md`) wraps
  calls to this agent; the agent itself stays provider-agnostic.

### Acceptance Criteria

- Agent class exists at `app/Ai/Agents/SchoolMessageProcessor.php` and
  implements the required contracts.
- Structured schema matches the shape described above exactly.
- System instructions include today's date and day of week interpolation.
- Prompting the agent with any of the sample Turkish messages returns a valid
  response that passes schema validation.
- Money/item/homework/event reminders are generated per the rules above.
- Informational messages yield empty `tasks` but populated translations and
  summary.
- Feature/unit tests with `Agent::fake()` cover:
  - money task extraction with amount and two reminders,
  - homework task extraction with Saturday/Sunday reminders,
  - multi-task message producing multiple task entries,
  - informational message yielding empty tasks array,
  - event task producing three reminders.

## Status

done

## Commits

- f719392d6c0ea90174de73a9bf3a2378396323be
