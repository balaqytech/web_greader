<?php

namespace App\Actions\Applications;

use App\DTOs\Application\CreateApplicationDTO;
use App\Models\Application;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

final class ConvertLeadToApplicationAction
{
    public function __construct(
        private CreateApplicationAction $createAction
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

            return $application->fresh();
        });
    }
}
