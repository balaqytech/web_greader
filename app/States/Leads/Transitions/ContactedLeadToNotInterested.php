<?php

namespace App\States\Leads\Transitions;

use App\Enums\LeadContactMethod;
use App\Enums\LeadContactResult;
use App\Models\Lead;
use App\States\Leads\NotInterested;
use Spatie\ModelStates\Transition;

class ContactedLeadToNotInterested extends Transition
{
    public function __construct(
        private readonly Lead $lead,
        private readonly string $contactedBy,
        private readonly LeadContactMethod $contactMethod,
        private readonly ?string $notes = null,
    ) {}

    public function handle(): Lead
    {
        $this->lead->contacts()->create([
            'contacted_by' => $this->contactedBy,
            'contact_method' => $this->contactMethod,
            'contact_result' => LeadContactResult::NotInterested,
            'notes' => $this->notes,
            'contacted_at' => now(),
        ]);

        $this->lead->forceFill(['status' => NotInterested::$name])->save();

        return $this->lead->refresh();
    }
}
