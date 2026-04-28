<?php

namespace App\Actions\Applications;

use App\DTOs\Application\CreateApplicationDTO;
use App\Models\Application;
use App\Models\Lead;
use App\Services\ExistingStudentLookupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConvertLeadToApplicationAction
{
    public function __construct(
        private CreateApplicationAction $createAction,
        private ExistingStudentLookupService $lookupService,
        private PrefillApplicationFromStudentAction $prefillAction,
    ) {}

    /**
     * Convert an Interested lead into a pending Application.
     * Pre-fills available lead data so the family only fills in missing fields.
     */
    public function execute(Lead $lead): Application
    {
        return DB::transaction(function () use ($lead) {
            $application = $this->createAction->execute(
                CreateApplicationDTO::fromLead($lead)
            );

            $application->lead()->associate($lead);

            $existingStudent = $this->lookupService->findExistingStudent($lead);

            if ($existingStudent) {
                $this->prefillAction->execute($application, $existingStudent);
                $application->refresh();

                try {
                    // Check if all required fields are filled. If yes, transition to DataComplete.
                    // This mirrors the old logic of submit action validation.
                    $requiredFields = [
                        'student_gender', 'student_birth_date', 'student_civil_number',
                        'student_state', 'student_governorate', 'student_village',
                        'student_house_number', 'student_parents_social_status',
                        'father_name', 'father_phone', 'father_id_number',
                        'mother_name', 'mother_phone', 'mother_id_number',
                    ];
                    
                    $isComplete = true;
                    foreach ($requiredFields as $field) {
                        if (empty($application->{$field})) {
                            $isComplete = false;
                            break;
                        }
                    }

                    if ($isComplete) {
                        $application->status->transitionTo(\App\States\Applications\DataComplete::class);
                    }
                } catch (\Exception $e) {
                    // Leave in pending registration
                }
            }

            return $application->fresh();
        });
    }
}
