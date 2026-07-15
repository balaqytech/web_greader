<?php

namespace Database\Factories;

use App\Enums\Gender;
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
            'state' => fake()->city(),
            'governorate' => fake()->city(),
            'village' => fake()->city(),
            'house_number' => fake()->buildingNumber(),
            'parents_social_status' => fake()->word(),
            'father_data' => [
                'name' => fake()->name('male'),
                'phone' => fake()->phoneNumber(),
                'email' => fake()->safeEmail(),
                'id_number' => fake()->numerify('########'),
                'occupation' => fake()->jobTitle(),
                'work_address' => fake()->address(),
                'work_phone' => fake()->phoneNumber(),
                'is_guardian' => true,
            ],
            'mother_data' => [
                'name' => fake()->name('female'),
                'phone' => fake()->phoneNumber(),
                'email' => fake()->safeEmail(),
                'id_number' => fake()->numerify('########'),
                'occupation' => fake()->jobTitle(),
                'work_address' => fake()->address(),
                'work_phone' => fake()->phoneNumber(),
                'is_guardian' => false,
            ],
            'relative_data' => [
                'name' => fake()->name(),
                'phone' => fake()->phoneNumber(),
                'email' => fake()->safeEmail(),
                'id_number' => fake()->numerify('########'),
                'occupation' => fake()->jobTitle(),
                'work_address' => fake()->address(),
                'work_phone' => fake()->phoneNumber(),
            ],
        ];
    }
}
