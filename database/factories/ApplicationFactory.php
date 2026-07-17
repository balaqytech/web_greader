<?php

namespace Database\Factories;

use App\Actions\Contracts\BuildContractSnapshotAction;
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
use App\States\Applications\CorrectionRequested;
use App\States\Applications\Rejected;
use App\States\Contracts\Generated;
use App\States\Contracts\Signed;
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
            'is_transfer_student' => false,

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

    /**
     * Guarantees the program is priced for the application's *actual* branch before any
     * contract snapshot is built. Tests routinely override `branch_id` to a pre-made branch the
     * definition's program was never attached to; without this, building a version (afterCreating
     * or via the generation transition) would hit a null program-branch pivot in `branchPrice`.
     * Registered here so it runs before the state helpers' own afterCreating callbacks.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Application $application) {
            if ($application->program === null || $application->branch_id === null) {
                return;
            }

            $alreadyPriced = $application->program->branches()
                ->where('branch_id', $application->branch_id)
                ->exists();

            if (! $alreadyPriced) {
                $application->program->branches()->attach($application->branch_id, ['price' => 0]);
            }
        });
    }

    public function transferStudent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_transfer_student' => true,
        ]);
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
            $this->createContractVersion($application, signed: false);
        });
    }

    public function awaitingBranchReview(bool $signed = true): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AwaitingBranchReview::$name,
        ])->afterCreating(function (Application $application) use ($signed) {
            $this->createContractVersion($application, signed: $signed);
        });
    }

    /**
     * Creates the next contract version for an application directly, so state helpers can seed
     * post-signature states without walking the generation transition. Populates the immutable
     * snapshot/body/hash from the authoritative builder so the row matches a real generation.
     */
    public function createContractVersion(Application $application, bool $signed): void
    {
        $snapshot = app(BuildContractSnapshotAction::class)->handle($application);

        $version = (int) ($application->contracts()->max('version') ?? 0) + 1;

        $application->contracts()->create([
            'version' => $version,
            'status' => $signed ? Signed::class : Generated::class,
            'data_snapshot' => $snapshot->toArray(),
            'rendered_body' => $snapshot->renderedBody,
            'template_hash' => $snapshot->templateHash,
            'token' => Str::random(64),
            'token_expires_at' => now()->addDays(7),
            'signed_at' => $signed ? now() : null,
            'signed_by_applicant' => $signed,
            'file_path' => $signed ? 'contracts/signed.pdf' : null,
            'signature_path' => $signed ? 'contracts/signatures/signed.png' : null,
        ]);
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

    public function correctionRequested(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CorrectionRequested::$name,
        ]);
    }
}
