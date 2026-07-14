<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\Support\Applications\LockApplication;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Moves a signed application into branch review. The row is locked and its persisted state
 * re-verified before writing, so a stale replay cannot repeat the transition. Rejects a
 * missing or unsigned contract; state change and activity are written in one transaction.
 */
class AwaitingContractSignatureToAwaitingBranchReview extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        return DB::transaction(function () {
            $application = LockApplication::inState($this->application, AwaitingContractSignature::class);

            $contract = $application->contract()->lockForUpdate()->first();

            if ($contract === null || ! $contract->isSignedOff()) {
                throw new ApplicationIncompleteException(__('alerts.application.contract_not_signed'));
            }

            $application->status = AwaitingBranchReview::class;
            $application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $application,
                AwaitingContractSignature::$name,
                AwaitingBranchReview::$name,
                $this->notes,
            );

            return $application;
        });
    }
}
