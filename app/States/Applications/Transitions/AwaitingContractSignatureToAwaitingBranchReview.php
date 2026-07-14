<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Moves a signed application into branch review. Rejects a missing or unsigned contract:
 * signing (electronic or uploaded) must have persisted the signed artifact before this
 * transition runs. State change and activity are written in one transaction.
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
            // Read the contract fresh (not via a possibly-stale cached relation) so the
            // guard reflects a signature persisted moments earlier in the same flow.
            $contract = $this->application->contract()->first();

            if ($contract === null || ! $contract->isSignedOff()) {
                throw new ApplicationIncompleteException(__('alerts.application.contract_not_signed'));
            }

            $fromState = AwaitingContractSignature::$name;

            $this->application->status = AwaitingBranchReview::class;
            $this->application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $this->application,
                $fromState,
                AwaitingBranchReview::$name,
                $this->notes,
            );

            return $this->application;
        });
    }
}
