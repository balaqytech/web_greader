<?php

namespace App\Support\Applications;

use App\Exceptions\StaleApplicationStateException;
use App\Models\Application;
use App\Models\Scopes\BranchScope;

/**
 * Serialization primitive for state-changing application operations. Inside a transaction,
 * it re-reads the application row with a `FOR UPDATE` lock (ignoring BranchScope so the row
 * is always found) and verifies the persisted source state before any write happens. A
 * stale caller — one whose in-memory state object is out of date — is rejected.
 */
final class LockApplication
{
    /**
     * @param  class-string  $expectedState
     */
    public static function inState(Application $application, string $expectedState): Application
    {
        $fresh = Application::withoutGlobalScope(BranchScope::class)
            ->whereKey($application->getKey())
            ->lockForUpdate()
            ->first();

        if ($fresh === null || ! $fresh->status instanceof $expectedState) {
            throw StaleApplicationStateException::make(
                $application,
                $expectedState,
                $fresh?->status ?? new \stdClass,
            );
        }

        return $fresh;
    }
}
