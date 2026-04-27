<?php

namespace App\States\Applications\Transitions;

use App\Models\Application;
use App\States\Applications\UnderReview;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

class PendingRegistrationToUnderReview extends Transition
{
    public function __construct(
        private readonly Application $application,
        private readonly ?int $transitionedBy = null,
        private readonly ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        $fromState = $this->application->status::$name;

        DB::transaction(function () use ($fromState) {
            $this->application->forceFill(['status' => UnderReview::$name])->save();

            $this->application->activities()->create([
                'transitioned_by' => $this->transitionedBy,
                'from_state' => $fromState,
                'to_state' => UnderReview::$name,
                'notes' => $this->notes,
                'transitioned_at' => now(),
            ]);
        });

        return $this->application->fresh();
    }
}
