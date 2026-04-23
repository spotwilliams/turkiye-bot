<?php

namespace Database\Factories;

use App\Models\FamilyMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'role' => $this->faker->randomElement(['father', 'mother']),
            'telegram_user_id' => $this->faker->unique()->numberBetween(100_000, 999_999_999),
            'telegram_chat_id' => $this->faker->numberBetween(100_000, 999_999),
            'timezone' => 'Europe/Istanbul',
            'preferences' => null,
        ];
    }
}
