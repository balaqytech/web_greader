<?php

namespace Database\Factories;

use App\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'id_number' => fake()->unique()->numerify('########'),
            'occupation' => fake()->jobTitle(),
            'work_address' => fake()->address(),
            'work_phone' => fake()->phoneNumber(),
        ];
    }
}
