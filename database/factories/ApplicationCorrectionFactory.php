<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationCorrection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationCorrection>
 */
class ApplicationCorrectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'requested_by' => User::factory(),
            'reason' => fake()->sentence(),
            'checklist' => [
                ['item' => 'Fix the civil number', 'done' => false],
                ['item' => 'Correct the guardian name', 'done' => false],
            ],
            'data_before' => [
                'minimum' => [],
                'placeholders' => [],
                'meta' => ['backfilled' => false],
            ],
            'is_contract_relevant' => null,
            'requested_at' => now(),
            'completed_by' => null,
            'completed_at' => null,
        ];
    }

    public function completed(bool $contractRelevant = false): static
    {
        return $this->state(fn (array $attributes) => [
            'checklist' => collect($attributes['checklist'] ?? [])
                ->map(fn (array $item) => [...$item, 'done' => true])
                ->all(),
            'is_contract_relevant' => $contractRelevant,
            'completed_by' => User::factory(),
            'completed_at' => now(),
        ]);
    }
}
