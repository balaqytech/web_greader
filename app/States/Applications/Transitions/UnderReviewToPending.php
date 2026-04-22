<?php

namespace App\States\Applications\Transitions;

use App\Models\Application;
use App\States\Applications\PendingRegistration;
use Spatie\ModelStates\Transition;

class UnderReviewToPending extends Transition
{
    public function __construct(
        private readonly Application $application,
        private readonly ?string $notes = null,
        private readonly ?int $transitionedBy = null,
    ) {}

    public function handle(): Application
    {
        $fromState = $this->application->status::$name;

        $this->application->forceFill([
            'status' => PendingRegistration::$name,
            'rejection_reason' => $this->notes,
        ])->save();

        $this->application->activities()->create([
            'transitioned_by' => $this->transitionedBy,
            'from_state' => $fromState,
            'to_state' => PendingRegistration::$name,
            'notes' => $this->notes,
            'transitioned_at' => now(),
        ]);

        return $this->application->refresh();
    }
}
