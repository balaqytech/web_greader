<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\AcceptApplicationAction;
use App\Actions\Applications\RecordApplicationActivityAction;
use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingBranchReview;
use App\Support\Applications\LockApplication;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Baseline atomic acceptance (§3.5, §4.1). The row is locked and its persisted state
 * re-verified before writing, so a stale replay cannot repeat acceptance (double-creating
 * students/guardians). Guards a signed contract, then upserts guardian/student/contacts,
 * back-links student_id, flips the state, and records the approver in one transaction.
 * The version-aware guard is added with contract versioning in a later phase.
 */
class AwaitingBranchReviewToAccepted extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        return DB::transaction(function () {
            $application = LockApplication::inState($this->application, AwaitingBranchReview::class);

            $contract = $application->contract()->first();

            if ($contract === null || ! $contract->isSignedOff()) {
                throw new ApplicationIncompleteException(__('alerts.application.contract_not_signed'));
            }

            app(AcceptApplicationAction::class)->handle($application);

            $application->status = Accepted::class;
            $application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $application,
                AwaitingBranchReview::$name,
                Accepted::$name,
                $this->notes,
            );

            return $application;
        });
    }
}
