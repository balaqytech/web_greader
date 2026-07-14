<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\GenerateApplicationContractAction;
use App\Actions\Applications\RecordApplicationActivityAction;
use App\Actions\Applications\ValidateApplicationCompletionAction;
use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingContractSignature;
use App\Support\Applications\LockApplication;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Generates the contract and moves an application into signature. The row is locked and its
 * persisted state re-verified before writing, so a stale replay cannot rotate the contract
 * token after the application has already moved on.
 */
class AwaitingApplicationCompletionToAwaitingContractSignature extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        return DB::transaction(function () {
            $application = LockApplication::inState($this->application, AwaitingApplicationCompletion::class);

            app(ValidateApplicationCompletionAction::class)->handle($application);
            app(GenerateApplicationContractAction::class)->handle($application);

            $application->status = AwaitingContractSignature::class;
            $application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $application,
                AwaitingApplicationCompletion::$name,
                AwaitingContractSignature::$name,
                $this->notes,
            );

            return $application;
        }, attempts: 3);
    }
}
