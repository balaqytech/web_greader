<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Models\Application;
use App\States\Applications\Cancelled;
use App\States\Applications\WaitingContractSignature;
use Spatie\ModelStates\Transition;

class WaitingContractSignatureToCancelled extends Transition
{
    public function __construct(public Application $application) {}

    public function handle(): Application
    {
        $fromState = WaitingContractSignature::getMorphClass();

        $this->application->status = Cancelled::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            Cancelled::getMorphClass(),
        );

        return $this->application;
    }
}
