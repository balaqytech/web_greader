<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Actions\Corrections\ClassifyCorrectionAction;
use App\Exceptions\CorrectionException;
use App\Models\Application;
use App\Models\ApplicationCorrection;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\CorrectionRequested;
use App\Support\Applications\LockApplication;
use App\Support\Corrections\Checklist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Completes a non-contract-relevant correction, returning the application to branch review
 * (§4.1). The row is locked, the open correction re-read, its checklist required complete, and
 * classification recomputed under lock — if it turns out contract-relevant this edge refuses
 * (the caller must go through the re-signature edge instead). Correction completion, state
 * change, and activity are written in one transaction.
 */
class CorrectionRequestedToAwaitingBranchReview extends Transition
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

            if (app(ClassifyCorrectionAction::class)->isContractRelevant($application)) {
                throw CorrectionException::isContractRelevant();
            }

            $correction->update([
                'completed_by' => Auth::id(),
                'completed_at' => now(),
                'is_contract_relevant' => false,
            ]);

            $application->status = AwaitingBranchReview::class;
            $application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $application,
                CorrectionRequested::$name,
                AwaitingBranchReview::$name,
                $this->notes,
            );

            return $application;
        }, attempts: 3);
    }
}
