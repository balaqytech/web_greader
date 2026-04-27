<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Program;
use App\Models\Season;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory()->create();
        $program = Program::factory()->create();
        $program->branches()->attach($branch, ['price' => 0]);
        $season = Season::factory()->create();

        return [
            'application_id' => Application::factory(),
            'guardian_id' => Guardian::factory(),
            'branch_id' => $branch->id,
            'season_id' => $season->id,
            'program_id' => $program->id,
            'name' => fake()->name(),
            'gender' => fake()->randomElement(Gender::cases()),
            'birth_date' => fake()->date(),
            'civil_number' => fake()->unique()->numerify('########'),
            'state' => fake()->state(),
            'governorate' => fake()->city(),
            'village' => fake()->city(),
            'house_number' => fake()->buildingNumber(),
            'parents_social_status' => fake()->word(),
        ];
    }
}
