<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Actions\Applications\RejectApplicationAction;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\Rejected;
use Spatie\ModelStates\Transition;

class AwaitingBranchReviewToRejected extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        $fromState = AwaitingBranchReview::$name;
        $reason = $this->application->rejection_reason ?? $this->notes ?? 'Rejected';

        app(RejectApplicationAction::class)->handle($this->application, $reason);

        $this->application->status = Rejected::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            Rejected::$name,
            $reason,
        );

        return $this->application;
    }
}
