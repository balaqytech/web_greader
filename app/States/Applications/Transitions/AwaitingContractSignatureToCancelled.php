<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Models\Application;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\Cancelled;
use Spatie\ModelStates\Transition;

class AwaitingContractSignatureToCancelled extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        $fromState = AwaitingContractSignature::$name;

        $this->application->status = Cancelled::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            Cancelled::$name,
            $this->notes,
        );

        return $this->application;
    }
}
