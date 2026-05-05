<?php

namespace Database\Factories;

use App\Enums\ContactType;
use App\Enums\Gender;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Lead;
//
use App\Models\Program;
use App\States\Applications\Submitted;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContractSignature;
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
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Application $application) {
            if (! $application->applicationStudent()->exists()) {
                $application->applicationStudent()->create([
                    'name' => fake()->name(),
                    'gender' => fake()->randomElement(Gender::cases()),
                    'birth_date' => fake()->date(),
                    'civil_number' => fake()->unique()->numerify('########'),
                    'state' => fake()->state(),
                    'governorate' => fake()->city(),
                    'village' => fake()->city(),
                    'house_number' => fake()->buildingNumber(),
                    'parents_social_status' => fake()->word(),
                ]);
            }

            if ($application->contacts()->count() === 0) {
                $application->contacts()->create([
                    'type' => ContactType::Father,
                    'name' => fake()->name('male'),
                    'phone' => fake()->phoneNumber(),
                    'id_number' => fake()->unique()->numerify('########'),
                    'is_guardian' => true,
                ]);

                $application->contacts()->create([
                    'type' => ContactType::Mother,
                    'name' => fake()->name('female'),
                    'phone' => fake()->phoneNumber(),
                ]);

                $application->contacts()->create([
                    'type' => ContactType::Relative,
                    'name' => fake()->name(),
                    'phone' => fake()->phoneNumber(),
                ]);
            }
        });
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Submitted::$name,
        ]);
    }

    public function waitingContractSignature(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WaitingContractSignature::$name,
        ])->afterCreating(function (Application $application) {
            $application->contract()->create([
                'token' => Str::uuid()->toString(),
                'token_expires_at' => now()->addDays(7),
            ]);
        });
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UnderReview::$name,
        ])->afterCreating(function (Application $application) {
            $application->contract()->create([
                'token' => Str::uuid()->toString(),
                'token_expires_at' => now()->addDays(7),
                'signed_at' => now(),
                'signed_by_applicant' => true,
            ]);
        });
    }
}
