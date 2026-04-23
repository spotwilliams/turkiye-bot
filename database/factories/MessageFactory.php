<?php

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'telegram_chat_id' => $this->faker->numberBetween(100_000, 999_999),
            'telegram_message_id' => $this->faker->numberBetween(1, 10_000),
            'original_text' => $this->faker->paragraph(),
            'translation_en' => $this->faker->paragraph(),
            'translation_es' => $this->faker->paragraph(),
            'summary' => $this->faker->sentence(),
            'raw_processor_response' => null,
            'processed_at' => now(),
        ];
    }
}
