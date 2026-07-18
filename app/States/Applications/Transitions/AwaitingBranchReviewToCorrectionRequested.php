<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Actions\Contracts\BuildContractSnapshotAction;
use App\Exceptions\ApplicationIncompleteException;
use App\Exceptions\CorrectionException;
use App\Models\Application;
use App\Models\User;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\CorrectionRequested;
use App\Support\Applications\LockApplication;
use App\Support\Corrections\Checklist;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Branch staff request a correction on a signed application (§4.1). The row is locked and its
 * persisted state re-verified, an active signed contract is required, a trimmed reason and at
 * least one distinct nonblank checklist item are required, and no correction may already be
 * open. The confirmed-minimum + placeholder snapshot at request time is frozen into
 * `data_before`. Correction row, state change, and activity are written in one transaction, so
 * two concurrent requests can only ever produce one open correction.
 *
 * The acting user is passed in explicitly rather than read from ambient Auth: it is the sole
 * source for both `requested_by` and the activity actor, so the domain action (and a future
 * Sanctum service account) drives it deterministically. `$actor` is nullable only because
 * Spatie instantiates the transition with the model alone for `canTransitionTo()`; the guard
 * below, not the signature, is the enforcement.
 */
class AwaitingBranchReviewToCorrectionRequested extends Transition
{
    /**
     * @param  array<int, mixed>  $items
     */
    public function __construct(
        public Application $application,
        public ?User $actor = null,
        public ?string $reason = null,
        public array $items = [],
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        if ($this->actor === null) {
            throw CorrectionException::actorRequired();
        }

        return DB::transaction(function () {
            $application = LockApplication::inState($this->application, AwaitingBranchReview::class);

            $reason = trim((string) $this->reason);

            if ($reason === '') {
                throw CorrectionException::reasonRequired();
            }

            $checklist = Checklist::fromItems($this->items);

            $contract = $application->activeContract()->lockForUpdate()->first();

            if ($contract === null || ! $contract->isSignedOff()) {
                throw new ApplicationIncompleteException(__('alerts.application.contract_not_signed'));
            }

            if ($application->openCorrection()->lockForUpdate()->exists()) {
                throw CorrectionException::alreadyOpen();
            }

            $application->corrections()->create([
                'requested_by' => $this->actor->getKey(),
                'reason' => $reason,
                'checklist' => $checklist,
                'data_before' => app(BuildContractSnapshotAction::class)->handle($application)->toArray(),
                'requested_at' => now(),
            ]);

            $application->status = CorrectionRequested::class;
            $application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $application,
                AwaitingBranchReview::$name,
                CorrectionRequested::$name,
                $this->notes ?? $reason,
                $this->actor->getKey(),
            );

            return $application;
        }, attempts: 3);
    }
}
