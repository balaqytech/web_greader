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
     * @param  LeadState  $to_status
     * @param  string|null  $followUpAt
     */
    public function execute(
        string $to_status,
        Lead $lead,
        string $contactedBy,
        ?LeadContactMethod $contactMethod,
        ?string $notes = null,
    ): Lead {
        $statusClass = get_class($lead->status);
        $status = new $statusClass($lead);

        $status->transitionTo(
            $to_status,
            contactedBy: $contactedBy,
            contactMethod: $contactMethod,
            notes: $notes,
        );

        return $lead;
    }
}
