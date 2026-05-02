<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RejectApplicationAction;
use App\Models\Application;
use App\States\Applications\Rejected;
use Spatie\ModelStates\Transition;

class UnderReviewToRejected extends Transition
{
    public function __construct(public Application $application) {}

    public function handle(): Application
    {
        app(RejectApplicationAction::class)->handle($this->application, $this->application->rejection_reason ?? 'Rejected');

        $this->application->status = Rejected::class;
        $this->application->rejected_at = now();
        $this->application->save();

        return $this->application;
    }
}
