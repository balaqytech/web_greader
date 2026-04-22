<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\States\Applications\DataComplete;
use App\States\Applications\UnderReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory()->create();
        $program = Program::factory()->create();
        $program->branches()->attach($branch, ['price' => 0]);

        $lead = Lead::factory()->contactedLead()->create([
            'branch_id' => $branch->id,
            'program_id' => $program->id,
        ]);

        return [
            'lead_id' => $lead->id,
            'season_id' => $lead->season_id,
            'program_id' => $program->id,
            'branch_id' => $branch->id,
            'student_name' => fake()->name(),
            'student_gender' => fake()->randomElement(Gender::cases()),
        ];
    }

    public function dataComplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DataComplete::$name,
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UnderReview::$name,
        ]);
    }
}
