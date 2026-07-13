<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\Source;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\Cancelled;
use App\States\Applications\Rejected;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Real target factory against the flat `applications` schema. State helpers place an
     * application directly into any baseline state (the payment-gated fee transition is
     * not wired in Phase 0, so tests seed post-fee states directly).
     *
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
            'source' => Source::DASHBOARD,
            'status' => AwaitingRegistrationFee::$name,

            'student_name' => fake()->name(),
            'student_gender' => fake()->randomElement(Gender::cases()),
            'student_birth_date' => fake()->date(),
            'student_civil_number' => fake()->unique()->numerify('########'),
            'student_state' => fake()->city(),
            'student_governorate' => fake()->city(),
            'student_village' => fake()->city(),
            'student_house_number' => fake()->buildingNumber(),
            'student_parents_social_status' => fake()->word(),
            'relationship_with_guardian' => GuardianRelationship::Father,

            'father_name' => fake()->name('male'),
            'father_phone' => fake()->phoneNumber(),
            'father_email' => fake()->safeEmail(),
            'father_id_number' => fake()->unique()->numerify('########'),
            'father_occupation' => fake()->jobTitle(),
            'father_is_guardian' => true,

            'mother_name' => fake()->name('female'),
            'mother_phone' => fake()->phoneNumber(),
            'mother_id_number' => fake()->unique()->numerify('########'),
            'mother_is_guardian' => false,
        ];
    }

    public function awaitingRegistrationFee(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AwaitingRegistrationFee::$name,
        ]);
    }

    public function awaitingApplicationCompletion(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AwaitingApplicationCompletion::$name,
        ]);
    }

    public function awaitingContractSignature(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AwaitingContractSignature::$name,
        ])->afterCreating(function (Application $application) {
            $application->contract()->create([
                'token' => Str::random(64),
                'token_expires_at' => now()->addDays(7),
            ]);
        });
    }

    public function awaitingBranchReview(bool $signed = true): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AwaitingBranchReview::$name,
        ])->afterCreating(function (Application $application) use ($signed) {
            $application->contract()->create([
                'token' => Str::random(64),
                'token_expires_at' => now()->addDays(7),
                'signed_at' => $signed ? now() : null,
                'signed_by_applicant' => $signed,
            ]);
        });
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Accepted::$name,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Rejected::$name,
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Cancelled::$name,
        ]);
    }
}
