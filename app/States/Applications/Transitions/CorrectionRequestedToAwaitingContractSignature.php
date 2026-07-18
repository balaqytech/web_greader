<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\GenerateApplicationContractAction;
use App\Actions\Applications\RecordApplicationActivityAction;
use App\Actions\Applications\ValidateApplicationCompletionAction;
use App\Actions\Corrections\ClassifyCorrectionAction;
use App\Exceptions\CorrectionException;
use App\Models\Application;
use App\Models\ApplicationCorrection;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\CorrectionRequested;
use App\Support\Applications\LockApplication;
use App\Support\Corrections\Checklist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Completes a contract-relevant correction: the signed version is superseded, a new version is
 * generated, and the application returns to signature for a fresh signing (§4.1). The row is
 * locked, the open correction re-read, its checklist required complete, completion re-validated,
 * and classification recomputed under lock — if it turns out non-relevant this edge refuses (the
 * caller must return straight to review instead). Supersession, generation, correction
 * completion, state change, and activity are written in one transaction, so a concurrent
 * duplicate completion yields one successor contract, not two.
 */
class CorrectionRequestedToAwaitingContractSignature extends Transition
{
    public function __construct(
        public Application $application,
        public ?ApplicationCorrection $correction = null,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        return DB::transaction(function () {
            $application = LockApplication::inState($this->application, CorrectionRequested::class);

            $correction = $application->openCorrection()->lockForUpdate()->first();

            if ($correction === null) {
                throw CorrectionException::noneOpen();
            }

            if (! Checklist::allDone($correction->checklist)) {
                throw CorrectionException::checklistIncomplete();
            }

            if (! app(ClassifyCorrectionAction::class)->isContractRelevant($application)) {
                throw CorrectionException::notContractRelevant();
            }

            app(ValidateApplicationCompletionAction::class)->handle($application);

            // Supersedes the active signed version and creates the next generated version under
            // the application -> contracts lock order.
            app(GenerateApplicationContractAction::class)->handle($application);

            $correction->update([
                'completed_by' => Auth::id(),
                'completed_at' => now(),
                'is_contract_relevant' => true,
            ]);

            $application->status = AwaitingContractSignature::class;
            $application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $application,
                CorrectionRequested::$name,
                AwaitingContractSignature::$name,
                $this->notes,
            );

            return $application;
        }, attempts: 3);
    }
}
