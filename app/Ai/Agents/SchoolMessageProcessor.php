<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class SchoolMessageProcessor implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public string $currentDate = '',
        public string $dayOfWeek = '',
    ) {
        $this->currentDate = $this->currentDate !== '' ? $this->currentDate : now()->format('Y-m-d');
        $this->dayOfWeek = $this->dayOfWeek !== '' ? $this->dayOfWeek : now()->format('l');
    }

    /**
     * Resolve the text model from config so provider/model stay env-driven.
     */
    public function model(): ?string
    {
        return config('ai.default_text_model');
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<INSTRUCTIONS
        You are a school message processing assistant for parents living in Turkey.

        You receive messages from school teachers written in Turkish.

        For every message, you must:
        1. TRANSLATE the full message into English and Spanish.
        2. SUMMARIZE what the school is communicating in 1-2 sentences (in English).
        3. EXTRACT every actionable task that requires parents to do something.

        For each task, determine:
        - A clear, short description (in English)
        - A category: money, homework, item, event, or other
        - The exact due date (calculate from context + today's date: {$this->currentDate}, {$this->dayOfWeek})
        - A due time if specified, otherwise null
        - A monetary amount if applicable, otherwise null

        If the message is just informational with no action required, return an empty tasks array.
        INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'translation_en' => $schema->string()
                ->description('Full English translation of the message')
                ->required(),

            'translation_es' => $schema->string()
                ->description('Full Spanish translation of the message')
                ->required(),

            'summary' => $schema->string()
                ->description('1-2 sentence summary in English')
                ->required(),

            'tasks' => $schema->array()
                ->items(
                    $schema->object(fn (JsonSchema $s) => [
                        'description' => $s->string()
                            ->description('Short task description in English')
                            ->required(),

                        'category' => $s->string()
                            ->enum(['money', 'homework', 'item', 'event', 'other'])
                            ->required(),

                        'due_date' => $s->string()
                            ->description('Due date in YYYY-MM-DD format')
                            ->required(),

                        'due_time' => $s->string()
                            ->description('Due time in HH:MM format or null'),

                        'amount' => $s->number()
                            ->description('Monetary amount or null'),

                        'currency' => $s->string()
                            ->description('ISO currency code, defaults to TRY'),
                    ])
                )
                ->required(),
        ];
    }
}
