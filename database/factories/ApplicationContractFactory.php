<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationContract;
use App\States\Contracts\Generated;
use App\States\Contracts\Signed;
use App\States\Contracts\Superseded;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApplicationContract>
 */
class ApplicationContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'version' => 1,
            'status' => Generated::class,
            'data_snapshot' => [
                'minimum' => [
                    'student_name' => fake()->name(),
                    'student_civil_number' => fake()->numerify('########'),
                ],
                'placeholders' => [],
                'meta' => ['backfilled' => false],
            ],
            'rendered_body' => fake()->paragraph(),
            'template_hash' => hash('sha256', fake()->sentence()),
            'token' => Str::random(64),
            'token_expires_at' => now()->addDays(7),
            'signed_at' => null,
            'signed_by_applicant' => false,
            'file_path' => null,
            'signature_path' => null,
        ];
    }

    public function signed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Signed::class,
            'signed_at' => now(),
            'signed_by_applicant' => true,
            'file_path' => 'contracts/signed.pdf',
            'signature_path' => 'contracts/signatures/signed.png',
        ]);
    }

    public function superseded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Superseded::class,
            'token' => null,
            'token_expires_at' => null,
            'superseded_at' => now(),
        ]);
    }
}
