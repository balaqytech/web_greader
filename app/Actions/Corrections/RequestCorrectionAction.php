<?php

declare(strict_types=1);

namespace App\Actions\Corrections;

use App\Models\Application;
use App\States\Applications\CorrectionRequested;

/**
 * Requests a correction on a signed application, reusable by the Filament action and (later)
 * the Phase 5 API. The atomic work — locking, guards, capturing `data_before`, creating the
 * correction, recording activity — lives in the AwaitingBranchReview -> CorrectionRequested
 * transition; this is the single entry point that drives it.
 *
 * @param  array<int, mixed>  $items
 */
final class RequestCorrectionAction
{
    public function handle(Application $application, string $reason, array $items, ?string $notes = null): Application
    {
        // transitionTo returns the row-locked instance the transition mutated (fetched without
        // BranchScope), so it is safe to return even when the acting user is on another branch.
        return $application->status->transitionTo(CorrectionRequested::class, $reason, $items, $notes);
    }
}
