<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\AcceptApplicationAction;
use App\Actions\Applications\RecordApplicationActivityAction;
use App\Events\ApplicationAccepted;
use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingBranchReview;
use App\States\Contracts\Signed;
use App\Support\Applications\LockApplication;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Atomic acceptance (§3.5, §4.1). The row is locked and its persisted state re-verified before
 * writing, so a stale replay cannot repeat acceptance (double-creating students/guardians).
 *
 * Version-aware guard: acceptance requires the **active** contract — the highest-version, still
 * non-superseded row (`activeContract()`) — to be in `Signed` state with a persisted artifact.
 * A historical signed version cannot satisfy acceptance once a newer version has been generated:
 * `activeContract()` then resolves to that newer `generated` (unsigned) version, which fails the
 * guard. Only after the newer version is itself signed does acceptance pass. Then it upserts
 * guardian/student/contacts, back-links student_id, flips the state, and records the approver in
 * one transaction.
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

            $contract = $application->activeContract()->lockForUpdate()->first();

            if ($contract === null || ! $contract->status instanceof Signed || ! $contract->isSignedOff()) {
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

            // Recorded in the outbox within this transaction — rolls back with the acceptance
            // if anything above fails.
            event(new ApplicationAccepted(
                $application->getKey(),
                $application->ref_no,
                $application->branch_id,
            ));

            return $application;
        }, attempts: 3);
    }
}
