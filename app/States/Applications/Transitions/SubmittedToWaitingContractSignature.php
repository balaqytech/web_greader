<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\GenerateApplicationContractAction;
use App\Actions\Applications\RecordApplicationActivityAction;
use App\Models\Application;
use App\States\Applications\Submitted;
use App\States\Applications\WaitingContractSignature;
use Spatie\ModelStates\Transition;

class SubmittedToWaitingContractSignature extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        app(GenerateApplicationContractAction::class)->handle($this->application);

        $fromState = Submitted::getMorphClass();

        $this->application->status = WaitingContractSignature::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            WaitingContractSignature::class,
            $this->notes,
        );

        return $this->application;
    }
}
