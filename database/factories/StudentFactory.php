<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Models\Branch;
use App\Models\Guardian;
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
        return [
            'guardian_id' => Guardian::factory(),
            'branch_id' => Branch::factory(),
            'name' => fake()->name(),
            'gender' => fake()->randomElement(Gender::cases()),
            'birth_date' => fake()->date(),
            'civil_number' => fake()->unique()->numerify('########'),
            // fake()->state() has no ar_SA provider (this app's configured
            // APP_FAKER_LOCALE); city() is locale-safe and used the same way elsewhere in
            // this factory for governorate/village.
            'state' => fake()->city(),
            'governorate' => fake()->city(),
            'village' => fake()->city(),
            'house_number' => fake()->buildingNumber(),
            'parents_social_status' => fake()->word(),
            'relationship_with_guardian' => fake()->randomElement(GuardianRelationship::cases()),
        ];
    }
}
