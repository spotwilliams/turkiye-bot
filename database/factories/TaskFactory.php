<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'telegram_chat_id' => $this->faker->numberBetween(100_000, 999_999),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['money', 'homework', 'item', 'event', 'other']),
            'due_date' => now()->addDays($this->faker->numberBetween(0, 14))->toDateString(),
            'due_time' => null,
            'amount' => null,
            'currency' => 'TRY',
            'assigned_to' => 'both',
            'status' => 'pending',
            'completed_at' => null,
        ];
    }
}
