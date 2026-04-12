<?php

namespace App\Actions\Leads;

use App\Enums\LeadContactMethod;
use App\Models\Lead;
use App\States\Leads\ContactedLead;
use App\States\Leads\Interested;
use App\States\Leads\NoResponse;
use App\States\Leads\NotInterested;
use App\States\Leads\Transitions\ContactedLeadToInterested;
use App\States\Leads\Transitions\ContactedLeadToNoResponse;
use App\States\Leads\Transitions\ContactedLeadToNotInterested;
use App\States\Leads\Transitions\NewLeadToContactedLead;

class TransitionLeadStateAction
{
    /**
     * Transition a lead to ContactedLead and record the contact attempt.
     */
    public function toContacted(
        Lead $lead,
        string $contactedBy,
        LeadContactMethod $contactMethod,
        ?string $notes = null,
        ?string $followUpAt = null,
    ): Lead {
        $lead->status->transitionTo(
            ContactedLead::class,
            contactedBy: $contactedBy,
            contactMethod: $contactMethod,
            notes: $notes,
            followUpAt: $followUpAt,
        );

        return $lead;
    }

    /**
     * Transition a lead to Interested and record the contact outcome.
     */
    public function toInterested(
        Lead $lead,
        string $contactedBy,
        LeadContactMethod $contactMethod,
        ?string $notes = null,
    ): Lead {
        $lead->status->transitionTo(
            Interested::class,
            contactedBy: $contactedBy,
            contactMethod: $contactMethod,
            notes: $notes,
        );

        return $lead;
    }

    /**
     * Transition a lead to NotInterested and record the contact outcome.
     */
    public function toNotInterested(
        Lead $lead,
        string $contactedBy,
        LeadContactMethod $contactMethod,
        ?string $notes = null,
    ): Lead {
        $lead->status->transitionTo(
            NotInterested::class,
            contactedBy: $contactedBy,
            contactMethod: $contactMethod,
            notes: $notes,
        );

        return $lead;
    }

    /**
     * Transition a lead to NoResponse and record the contact outcome.
     */
    public function toNoResponse(
        Lead $lead,
        string $contactedBy,
        LeadContactMethod $contactMethod,
        ?string $notes = null,
        ?string $followUpAt = null,
    ): Lead {
        $lead->status->transitionTo(
            NoResponse::class,
            contactedBy: $contactedBy,
            contactMethod: $contactMethod,
            notes: $notes,
            followUpAt: $followUpAt,
        );

        return $lead;
    }
}
