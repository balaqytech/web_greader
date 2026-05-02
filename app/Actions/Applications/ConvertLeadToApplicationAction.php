<?php

namespace App\Actions\Applications;

use App\DTOs\Application\CreateApplicationDTO;
use App\Models\Application;
use App\Models\Lead;
use App\Services\ExistingStudentLookupService;
use App\States\Applications\Submitted;
use Illuminate\Support\Facades\DB;

final class ConvertLeadToApplicationAction
{
    public function __construct(
        private CreateApplicationAction $createAction,
        private ExistingStudentLookupService $lookupService,
        private PrefillApplicationFromStudentAction $prefillAction,
    ) {}

    /**
     * Convert an Interested lead into a Draft Application.
     * Pre-fills available lead data so the family only fills in missing fields.
     * If a returning student is found and data is complete, transitions to Submitted.
     */
    public function execute(Lead $lead): Application
    {
        return DB::transaction(function () use ($lead) {
            $application = $this->createAction->execute(
                CreateApplicationDTO::fromLead($lead)
            );

            $existingStudent = $this->lookupService->findExistingStudent($lead);

            if ($existingStudent) {
                $this->prefillAction->handle($application, $existingStudent);
                $application->refresh();

                try {
                    // Attempt to transition to Submitted if data is complete.
                    // ValidateApplicationCompletionAction (called by the transition)
                    // will throw if data is incomplete — which is fine, we stay in Draft.
                    $application->status->transitionTo(Submitted::class);
                } catch (\Exception) {
                    // Data is incomplete — leave in Draft status
                }
            }

            return $application->fresh();
        });
    }
}
