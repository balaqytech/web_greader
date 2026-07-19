<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Events\ApplicationRejected;
use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\Rejected;
use App\Support\Applications\LockApplication;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Rejects an application under branch review. The row is locked and its persisted state
 * re-verified before writing, so a stale replay cannot repeat rejection or duplicate the
 * activity. A nonblank reason is required (supplied to the transition or already set on the
 * record); there is no default fallback. Reason, state, and activity persist in one
 * transaction.
 */
class AwaitingBranchReviewToRejected extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $reason = null,
    ) {}

    public function handle(): Application
    {
        return DB::transaction(function () {
            $application = LockApplication::inState($this->application, AwaitingBranchReview::class);

            $reason = filled($this->reason) ? $this->reason : $application->rejection_reason;

            if (blank($reason)) {
                throw new ApplicationIncompleteException(__('alerts.application.rejection_reason_required'));
            }

            $application->rejection_reason = $reason;
            $application->status = Rejected::class;
            $application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $application,
                AwaitingBranchReview::$name,
                Rejected::$name,
                $reason,
            );

            // The rejection reason is deliberately not carried on the event.
            event(new ApplicationRejected(
                $application->getKey(),
                $application->ref_no,
                $application->branch_id,
            ));

            return $application;
        }, attempts: 3);
    }
}
