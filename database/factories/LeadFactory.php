<?php

namespace Database\Factories;

use App\Enums\ProgramType;
use App\Enums\Source;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;
use App\States\Leads\ContactedLead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guardian_name' => fake()->name(),
            'student_name' => fake()->name(),
            'whatsapp' => fake()->phoneNumber(),
            'program_type' => ProgramType::Academic,
            'source' => Source::DASHBOARD,
            'branch_id' => Branch::factory(),
            'season_id' => Season::factory(),
            'program_id' => Program::factory(),
        ];
    }

    public function contactedLead(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactedLead::$name,
        ]);
    }
}
