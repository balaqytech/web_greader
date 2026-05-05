<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\AcceptApplicationAction;
use App\Actions\Applications\RecordApplicationActivityAction;
use App\Models\Application;
use App\States\Applications\Accepted;
use App\States\Applications\UnderReview;
use Spatie\ModelStates\Transition;

class UnderReviewToAccepted extends Transition
{
    public function __construct(public Application $application) {}

    public function handle(): Application
    {
        $fromState = UnderReview::getMorphClass();

        app(AcceptApplicationAction::class)->handle($this->application);

        $this->application->status = Accepted::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            Accepted::getMorphClass(),
        );

        return $this->application;
    }
}
