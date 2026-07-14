<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\Rejected;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Rejects an application under branch review. A nonblank reason is required (supplied to
 * the transition or already set on the record); there is no default fallback. Reason,
 * state, and activity are persisted in one transaction.
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
            $reason = filled($this->reason) ? $this->reason : $this->application->rejection_reason;

            if (blank($reason)) {
                throw new ApplicationIncompleteException(__('alerts.application.rejection_reason_required'));
            }

            $fromState = AwaitingBranchReview::$name;

            $this->application->rejection_reason = $reason;
            $this->application->status = Rejected::class;
            $this->application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $this->application,
                $fromState,
                Rejected::$name,
                $reason,
            );

            return $this->application;
        });
    }
}
