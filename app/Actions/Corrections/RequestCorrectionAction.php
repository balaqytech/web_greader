<?php

declare(strict_types=1);

namespace App\Actions\Corrections;

use App\Exceptions\CorrectionException;
use App\Models\Application;
use App\Models\User;
use App\States\Applications\CorrectionRequested;

/**
 * Requests a correction on a signed application, reusable by the Filament action and (later)
 * the Phase 5 API. The atomic work — locking, guards, capturing `data_before`, creating the
 * correction, recording activity — lives in the AwaitingBranchReview -> CorrectionRequested
 * transition; this is the single entry point that drives it.
 *
 * The acting user is explicit: callers pass the authenticated actor (Filament after policy
 * authorization; a Sanctum service account later), and it is rejected before any mutation if
 * absent. It is threaded through as the sole source for `requested_by` and the activity actor.
 */
final class RequestCorrectionAction
{
    /**
     * @param  array<int, mixed>  $items
     */
    public function handle(Application $application, ?User $actor, string $reason, array $items, ?string $notes = null): Application
    {
        if ($actor === null) {
            throw CorrectionException::actorRequired();
        }

        // transitionTo returns the row-locked instance the transition mutated (fetched without
        // BranchScope), so it is safe to return even when the acting user is on another branch.
        return $application->status->transitionTo(CorrectionRequested::class, $actor, $reason, $items, $notes);
    }
}
