<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\GenerateApplicationContractAction;
use App\Actions\Applications\RecordApplicationActivityAction;
use App\Actions\Applications\ValidateApplicationCompletionAction;
use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingContractSignature;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

class AwaitingApplicationCompletionToAwaitingContractSignature extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        return DB::transaction(function () {
            app(ValidateApplicationCompletionAction::class)->handle($this->application);
            app(GenerateApplicationContractAction::class)->handle($this->application);

            $fromState = AwaitingApplicationCompletion::$name;

            $this->application->status = AwaitingContractSignature::class;
            $this->application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $this->application,
                $fromState,
                AwaitingContractSignature::$name,
                $this->notes,
            );

            return $this->application;
        });
    }
}
