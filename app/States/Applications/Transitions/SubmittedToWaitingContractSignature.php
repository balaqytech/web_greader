<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\GenerateApplicationContractAction;
use App\Models\Application;
use App\States\Applications\WaitingContractSignature;
use Spatie\ModelStates\Transition;

class SubmittedToWaitingContractSignature extends Transition
{
    public function __construct(public Application $application) {}

    public function handle(): Application
    {
        app(GenerateApplicationContractAction::class)->handle($this->application);

        $this->application->status = WaitingContractSignature::class;
        $this->application->save();

        return $this->application;
    }
}
