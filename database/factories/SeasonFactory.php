<?php

namespace Database\Factories;

use App\Enums\ProgramType;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Academic ' . fake()->year() . '-' . fake()->year(),
            'type' => ProgramType::Academic,
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->toDateString(),
            'is_active' => true,
            'is_closed' => false,
        ];
    }

    public function academic(): static
    {
        return $this->state(fn() => [
            'name' => 'Academic 2025-2026',
            'type' => ProgramType::Academic,
            'start_date' => now()->subMonths(9)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
    }

    public function summer(): static
    {
        return $this->state(fn() => [
            'name' => 'Summer 2025',
            'type' => ProgramType::Summer,
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn() => [
            'is_active' => false,
            'is_closed' => true,
        ]);
    }
}
