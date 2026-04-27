<?php

namespace App\States\Applications\Transitions;

use App\Enums\GuardianRelationship;
use App\Models\Application;
use App\Models\Guardian;
use App\States\Applications\Accepted;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

class UnderReviewToAccepted extends Transition
{
    public function __construct(
        private readonly Application $application,
        private readonly ?int $transitionedBy = null,
        private readonly ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        $fromState = $this->application->status::$name;

        DB::transaction(function () use ($fromState) {
            $this->application->forceFill(['status' => Accepted::$name])->save();

            $guardian = $this->resolveGuardian();
            $this->createStudent($guardian);

            $this->application->activities()->create([
                'transitioned_by' => $this->transitionedBy,
                'from_state' => $fromState,
                'to_state' => Accepted::$name,
                'notes' => $this->notes,
                'transitioned_at' => now(),
            ]);
        });

        return $this->application->refresh();
    }

    /**
     * Resolve or create the guardian based on the is_guardian flags.
     * Deduplicates by id_number when available.
     */
    private function resolveGuardian(): Guardian
    {
        $guardianData = $this->buildGuardianData();

        if (! empty($guardianData['id_number'])) {
            return Guardian::firstOrCreate(
                ['id_number' => $guardianData['id_number']],
                $guardianData,
            );
        }

        return Guardian::create($guardianData);
    }

    /**
     * Build the guardian data array based on which parent is flagged as guardian.
     *
     * @return array<string, mixed>
     */
    private function buildGuardianData(): array
    {
        $app = $this->application;

        if ($app->father_is_guardian) {
            return [
                'name' => $app->father_name,
                'phone' => $app->father_phone,
                'email' => $app->father_email,
                'id_number' => $app->father_id_number,
                'occupation' => $app->father_occupation,
                'work_address' => $app->father_work_address,
                'work_phone' => $app->father_work_phone,
                'relationship' => GuardianRelationship::Father,
            ];
        }

        if ($app->mother_is_guardian) {
            return [
                'name' => $app->mother_name,
                'phone' => $app->mother_phone,
                'email' => $app->mother_email,
                'id_number' => $app->mother_id_number,
                'occupation' => $app->mother_occupation,
                'work_address' => $app->mother_work_address,
                'work_phone' => $app->mother_work_phone,
                'relationship' => GuardianRelationship::Mother,
            ];
        }

        return [
            'name' => $app->relative_name,
            'phone' => $app->relative_phone,
            'email' => $app->relative_email,
            'id_number' => $app->relative_id_number,
            'occupation' => $app->relative_occupation,
            'work_address' => $app->relative_work_address,
            'work_phone' => $app->relative_work_phone,
            'relationship' => GuardianRelationship::Relative,
        ];
    }

    /**
     * Create a student record from the application data.
     */
    private function createStudent(Guardian $guardian): void
    {
        $app = $this->application;

        $app->student()->create([
            'guardian_id' => $guardian->id,
            'branch_id' => $app->branch_id,
            'season_id' => $app->season_id,
            'program_id' => $app->program_id,
            'name' => $app->student_name,
            'gender' => $app->student_gender,
            'birth_date' => $app->student_birth_date,
            'civil_number' => $app->student_civil_number,
            'state' => $app->student_state,
            'governorate' => $app->student_governorate,
            'village' => $app->student_village,
            'house_number' => $app->student_house_number,
            'parents_social_status' => $app->student_parents_social_status,
            'father_data' => [
                'name' => $app->father_name,
                'phone' => $app->father_phone,
                'email' => $app->father_email,
                'id_number' => $app->father_id_number,
                'occupation' => $app->father_occupation,
                'work_address' => $app->father_work_address,
                'work_phone' => $app->father_work_phone,
                'is_guardian' => $app->father_is_guardian,
            ],
            'mother_data' => [
                'name' => $app->mother_name,
                'phone' => $app->mother_phone,
                'email' => $app->mother_email,
                'id_number' => $app->mother_id_number,
                'occupation' => $app->mother_occupation,
                'work_address' => $app->mother_work_address,
                'work_phone' => $app->mother_work_phone,
                'is_guardian' => $app->mother_is_guardian,
            ],
            'relative_data' => [
                'name' => $app->relative_name,
                'phone' => $app->relative_phone,
                'email' => $app->relative_email,
                'id_number' => $app->relative_id_number,
                'occupation' => $app->relative_occupation,
                'work_address' => $app->relative_work_address,
                'work_phone' => $app->relative_work_phone,
            ],
        ]);
    }
}
