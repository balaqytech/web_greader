<?php

namespace App\Actions\Applications;

use App\DTOs\Application\CreateApplicationDTO;
use App\Models\Application;
use App\Models\Lead;

final class ConvertLeadToApplicationAction
{
    public function __construct(
        private CreateApplicationAction $createAction,
    ) {}

    /**
     * Convert an Interested lead into an application at the start of the target
     * lifecycle (AwaitingRegistrationFee, the model default). Lead data is copied onto
     * the flat application columns via CreateApplicationDTO::fromLead. The application
     * advances past the fee gate only once payments exist (Phase 2).
     */
    public function execute(Lead $lead): Application
    {
        return $this->createAction->execute(
            CreateApplicationDTO::fromLead($lead)
        );
    }
}
