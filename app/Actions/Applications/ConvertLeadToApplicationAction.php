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
        private SubmitApplicationForReviewAction $submitAction,
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
                    $this->submitAction->execute($application);
                } catch (ValidationException $e) {
                    // If the prefilled data is missing some required fields,
                    // we simply leave the application in PendingRegistration state
                    // so the user can complete it manually.
                }
            }

            return $application->fresh();
        });
    }
}
