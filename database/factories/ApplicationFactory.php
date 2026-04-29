<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\States\Applications\DataComplete;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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

            // Student data
            'student_name' => fake()->name(),
            'student_gender' => fake()->randomElement(Gender::cases()),
            'student_birth_date' => fake()->date(),
            'student_civil_number' => fake()->unique()->numerify('########'),
            'student_state' => fake()->state(),
            'student_governorate' => fake()->city(),
            'student_village' => fake()->city(),
            'student_house_number' => fake()->buildingNumber(),
            'student_parents_social_status' => fake()->word(),

            // Father data
            'father_name' => fake()->name('male'),
            'father_phone' => fake()->phoneNumber(),
            'father_id_number' => fake()->unique()->numerify('########'),
            'father_is_guardian' => true,

            // Mother data
            'mother_name' => fake()->name('female'),
            'mother_phone' => fake()->phoneNumber(),
            'mother_id_number' => fake()->unique()->numerify('########'),

            // Relative data
            'relative_name' => fake()->name(),
            'relative_phone' => fake()->phoneNumber(),
        ];
    }

    public function dataComplete(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => DataComplete::$name,
        ]);
    }

    public function waitingContract(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => WaitingContract::$name,
            'contract_token' => Str::uuid()->toString(),
            'contract_token_expires_at' => now()->addDays(7),
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => UnderReview::$name,
        ]);
    }
}
