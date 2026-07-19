<?php

declare(strict_types=1);

namespace App\Actions\Applications;

use App\Models\Application;
use App\Models\Scopes\BranchScope;
use InvalidArgumentException;

/**
 * The single reference + guardian-phone match used by both payment initiation/receipt upload
 * and the status-check endpoint. The branchless service account has no branch to scope to, so
 * the lookup bypasses {@see BranchScope} — and only that scope. It returns a record only when
 * the reference exists AND the supplied phone normalizes to the application's guardian phone;
 * any other case (missing application, no guardian phone, unnormalizable or mismatched phone)
 * returns null so the caller can answer with one generic 404 that never reveals which half of
 * the pair was wrong.
 */
final class MatchApplicationForGuardianAction
{
    public function execute(string $reference, string $guardianPhone): ?Application
    {
        $application = Application::withoutGlobalScope(BranchScope::class)
            ->where('ref_no', $reference)
            ->first();

        if ($application === null || $application->guardian_phone === null) {
            return null;
        }

        try {
            $matches = normalize_phone_number($guardianPhone) === normalize_phone_number($application->guardian_phone);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $matches ? $application : null;
    }
}
