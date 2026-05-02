<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Actions\Applications\ValidateApplicationCompletionAction;
use App\Models\Application;
use App\States\Applications\Draft;
use App\States\Applications\Submitted;
use Spatie\ModelStates\Transition;

class DraftToSubmitted extends Transition
{
    public function __construct(public Application $application) {}

    public function handle(): Application
    {
        app(ValidateApplicationCompletionAction::class)->handle($this->application);

        $fromState = Draft::getMorphClass();

        $this->application->status = Submitted::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            Submitted::getMorphClass(),
        );

        return $this->application;
    }
}
