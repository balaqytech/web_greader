<?php

namespace Database\Factories;

use App\Enums\GuardianRelationship;
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
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'id_number' => fake()->unique()->numerify('########'),
            'occupation' => fake()->jobTitle(),
            'work_address' => fake()->address(),
            'work_phone' => fake()->phoneNumber(),
            'relationship' => fake()->randomElement(GuardianRelationship::cases()),
        ];
    }

    public function father(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => GuardianRelationship::Father,
        ]);
    }

    public function mother(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => GuardianRelationship::Mother,
        ]);
    }

    public function relative(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => GuardianRelationship::Relative,
        ]);
    }
}
