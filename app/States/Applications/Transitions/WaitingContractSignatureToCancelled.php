<?php

namespace App\States\Applications\Transitions;

use App\Models\Application;
use App\States\Applications\Cancelled;
use Spatie\ModelStates\Transition;

class WaitingContractSignatureToCancelled extends Transition
{
    public function __construct(public Application $application) {}

    public function handle(): Application
    {
        $this->application->status = Cancelled::class;
        $this->application->cancelled_at = now();
        $this->application->save();

        return $this->application;
    }
}
