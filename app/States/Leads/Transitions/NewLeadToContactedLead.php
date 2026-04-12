<?php

namespace App\States\Leads\Transitions;

use App\Enums\LeadContactMethod;
use App\Enums\LeadContactResult;
use App\Models\Lead;
use App\States\Leads\ContactedLead;
use Spatie\ModelStates\Transition;

class NewLeadToContactedLead extends Transition
{
    public function __construct(
        private readonly Lead $lead,
        private readonly string $contactedBy,
        private readonly LeadContactMethod $contactMethod,
        private readonly ?string $notes = null,
        private readonly ?string $followUpAt = null,
    ) {}

    public function handle(): Lead
    {
        $this->lead->contacts()->create([
            'contacted_by' => $this->contactedBy,
            'contact_method' => $this->contactMethod,
            'contact_result' => LeadContactResult::FollowUpLater,
            'notes' => $this->notes,
            'follow_up_at' => $this->followUpAt,
            'contacted_at' => now(),
        ]);

        $this->lead->forceFill(['status' => ContactedLead::$name])->save();

        return $this->lead->refresh();
    }
}
