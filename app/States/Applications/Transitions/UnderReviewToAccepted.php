<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\AcceptApplicationAction;
use App\Models\Application;
use App\States\Applications\Accepted;
use Spatie\ModelStates\Transition;

class UnderReviewToAccepted extends Transition
{
    public function __construct(public Application $application) {}

    public function handle(): Application
    {
        app(AcceptApplicationAction::class)->handle($this->application);

        // accepted_at and status are set inside AcceptApplicationAction
        $this->application->status = Accepted::class;
        $this->application->save();

        return $this->application;
    }
}
