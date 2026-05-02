<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Actions\Applications\RejectApplicationAction;
use App\Models\Application;
use App\States\Applications\Rejected;
use App\States\Applications\UnderReview;
use Spatie\ModelStates\Transition;

class UnderReviewToRejected extends Transition
{
    public function __construct(public Application $application) {}

    public function handle(): Application
    {
        $fromState = UnderReview::getMorphClass();
        $reason = $this->application->rejection_reason ?? 'Rejected';

        app(RejectApplicationAction::class)->handle($this->application, $reason);

        $this->application->status = Rejected::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            Rejected::getMorphClass(),
            $reason,
        );

        return $this->application;
    }
}
