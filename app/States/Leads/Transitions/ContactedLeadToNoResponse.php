<?php

namespace App\States\Leads\Transitions;

use App\Enums\LeadContactMethod;
use App\Enums\LeadContactResult;
use App\Models\Lead;
use App\States\Leads\NoResponse;
use Spatie\ModelStates\Transition;

class ContactedLeadToNoResponse extends Transition
{
    public function __construct(
        private readonly Lead $lead,
        private readonly ?string $contactedBy = null,
        private readonly ?LeadContactMethod $contactMethod = null,
        private readonly ?string $notes = null,
        private readonly ?string $followUpAt = null,
    ) {}

    public function handle(): Lead
    {
        $this->lead->contacts()->create([
            'contacted_by' => $this->contactedBy,
            'contact_method' => $this->contactMethod,
            'contact_result' => LeadContactResult::NoResponse,
            'notes' => $this->notes,
            'follow_up_at' => $this->followUpAt,
            'contacted_at' => now(),
        ]);

        $this->lead->forceFill(['status' => NoResponse::$name])->save();

        return $this->lead->refresh();
    }
}
