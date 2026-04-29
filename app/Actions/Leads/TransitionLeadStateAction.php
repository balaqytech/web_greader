<?php

namespace App\Actions\Leads;

use App\Enums\LeadContactMethod;
use App\Models\Lead;
use App\States\Leads\LeadState;

class TransitionLeadStateAction
{
    /**
     * Transition a lead to its transitionable states.
     * 
     * @param LeadState $to_status
     * @param Lead $lead
     * @param string $contactedBy
     * @param LeadContactMethod $contactMethod
     * @param string|null $notes
     * @param string|null $followUpAt
     * @return Lead
     */
    public function execute(
        string $to_status,
        Lead $lead,
        string $contactedBy,
        ?LeadContactMethod $contactMethod,
        ?string $notes = null,
    ): Lead {
        $lead->status->transitionTo(
            $to_status,
            contactedBy: $contactedBy,
            contactMethod: $contactMethod,
            notes: $notes,
        );

        return $lead;
    }
}
