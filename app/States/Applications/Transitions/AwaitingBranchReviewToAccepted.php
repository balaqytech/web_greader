<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\AcceptApplicationAction;
use App\Actions\Applications\RecordApplicationActivityAction;
use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingBranchReview;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Baseline atomic acceptance (§3.5, §4.1): guards that the current contract is signed,
 * then upserts guardian/student/contacts, back-links student_id, flips the state, and
 * records the approver — all in one transaction so a failure rolls everything back.
 * The version-aware (highest-version, non-superseded) guard is added with contract
 * versioning in a later phase (commit 15).
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
            $this->application->loadMissing('contract');

            if ($this->application->contract === null || $this->application->contract->signed_at === null) {
                throw new ApplicationIncompleteException(__('alerts.application.contract_not_signed'));
            }

            $fromState = AwaitingBranchReview::$name;

            app(AcceptApplicationAction::class)->handle($this->application);

            $this->application->status = Accepted::class;
            $this->application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $this->application,
                $fromState,
                Accepted::$name,
                $this->notes,
            );

            return $this->application;
        });
    }
}
