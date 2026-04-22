<?php

namespace App\States\Applications\Transitions;

use App\Models\Application;
use App\States\Applications\Rejected;
use Spatie\ModelStates\Transition;

class UnderReviewToRejected extends Transition
{
    public function __construct(
        private readonly Application $application,
        private readonly ?string $rejectionReason = null,
        private readonly ?int $transitionedBy = null,
    ) {}

    public function handle(): Application
    {
        $fromState = $this->application->status::$name;

        $this->application->forceFill([
            'status' => Rejected::$name,
            'rejection_reason' => $this->rejectionReason,
        ])->save();

        $this->application->activities()->create([
            'transitioned_by' => $this->transitionedBy,
            'from_state' => $fromState,
            'to_state' => Rejected::$name,
            'notes' => $this->rejectionReason,
            'transitioned_at' => now(),
        ]);

        return $this->application->refresh();
    }
}
