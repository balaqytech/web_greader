<?php

namespace App\States\Leads\Transitions;

use App\Actions\Applications\ConvertLeadToApplicationAction;
use App\Enums\LeadContactMethod;
use App\Enums\LeadContactResult;
use App\Exceptions\ProgramNotAvailableInBranchException;
use App\Models\Lead;
use App\States\Leads\Interested;
use Spatie\ModelStates\Transition;

class ContactedLeadToInterested extends Transition
{
    public function __construct(
        private readonly Lead $lead,
        private readonly ?string $contactedBy = null,
        private readonly ?LeadContactMethod $contactMethod = null,
        private readonly ?string $notes = null,
    ) {}

    /**
     * @throws ProgramNotAvailableInBranchException
     */
    public function handle(): Lead
    {
        if (! $this->lead->program->isAvailableIn($this->lead->branch)) {
            throw new ProgramNotAvailableInBranchException(
                $this->lead->program,
                $this->lead->branch,
            );
        }

        $this->lead->contacts()->create([
            'contacted_by' => $this->contactedBy,
            'contact_method' => $this->contactMethod,
            'contact_result' => LeadContactResult::Interested,
            'notes' => $this->notes,
            'contacted_at' => now(),
        ]);

        $this->lead->forceFill(['status' => Interested::$name])->save();

        app(ConvertLeadToApplicationAction::class)->execute($this->lead);

        return $this->lead->refresh();
    }
}
