<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\ValidateApplicationCompletionAction;
use App\Models\Application;
use App\States\Applications\Submitted;
use Spatie\ModelStates\Transition;

class DraftToSubmitted extends Transition
{
    public function __construct(public Application $application) {}

    public function handle(): Application
    {
        app(ValidateApplicationCompletionAction::class)->handle($this->application);

        $this->application->submitted_at = now();
        $this->application->status = Submitted::class;
        $this->application->save();

        return $this->application;
    }
}
