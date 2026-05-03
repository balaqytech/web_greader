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
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        app(ValidateApplicationCompletionAction::class)->handle($this->application);

        $fromState = Draft::$name;

        $this->application->status = Submitted::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            Submitted::$name,
            $this->notes,
        );

        return $this->application;
    }
}
