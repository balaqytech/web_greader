<?php

declare(strict_types=1);

namespace App\Actions\Corrections;

use App\Exceptions\CorrectionException;
use App\Models\Application;
use App\Models\User;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\CorrectionRequested;
use App\Support\Applications\LockApplication;
use App\Support\Corrections\Checklist;
use Illuminate\Support\Facades\DB;

/**
 * Completes the open correction, reusable by the Filament action and (later) the Phase 5 API.
 *
 * It marks the supplied item indexes done, requires every item complete, then classifies the
 * change under the application lock to pick the exit edge: contract-relevant corrections go to
 * re-signature (supersede + regenerate), everything else returns to branch review. The chosen
 * transition re-locks and re-classifies, so the decision is validated twice under lock and a
 * concurrent duplicate completion resolves to a single outcome.
 *
 * The acting user is explicit and rejected before any mutation if absent; it is threaded to the
 * chosen transition as the sole source for `completed_by` and the activity actor.
 */
final class CompleteCorrectionAction
{
    /**
     * @param  array<int, int|string>  $completedIndexes
     */
    public function handle(Application $application, ?User $actor, array $completedIndexes, ?string $notes = null): Application
    {
        if ($actor === null) {
            throw CorrectionException::actorRequired();
        }

        return DB::transaction(function () use ($application, $actor, $completedIndexes, $notes) {
            $locked = LockApplication::inState($application, CorrectionRequested::class);

            $correction = $locked->openCorrection()->lockForUpdate()->first();

            if ($correction === null) {
                throw CorrectionException::noneOpen();
            }

            $checklist = Checklist::markCompleted($correction->checklist, $completedIndexes);

            if (! Checklist::allDone($checklist)) {
                throw CorrectionException::checklistIncomplete();
            }

            $correction->update(['checklist' => $checklist]);

            $target = app(ClassifyCorrectionAction::class)->isContractRelevant($locked)
                ? AwaitingContractSignature::class
                : AwaitingBranchReview::class;

            // transitionTo returns the row-locked instance the transition mutated (fetched
            // without BranchScope), so it is safe to return even cross-branch.
            return $locked->status->transitionTo($target, $actor, $correction, $notes);
        }, attempts: 3);
    }
}
