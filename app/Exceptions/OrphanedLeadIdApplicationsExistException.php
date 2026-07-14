<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by the lead_id-tightening migration when it cannot (re-)add the foreign key because
 * applications reference a lead_id that no longer exists in `leads` — most plausibly left
 * behind by a prior local attempt that dropped the constraint but never finished restoring
 * it. Lists the count and a small sample of affected IDs so an operator can resolve them
 * before re-running the migration.
 */
class OrphanedLeadIdApplicationsExistException extends RuntimeException
{
    /**
     * @param  array<int, int|string>  $sampleIds
     */
    public static function make(int $count, array $sampleIds): self
    {
        return new self(
            "Cannot add the applications.lead_id foreign key: {$count} application(s) reference a "
            .'lead_id that does not exist in leads. Sample affected IDs: '.implode(', ', $sampleIds).'. '
            .'Resolve these (repoint to a valid lead or investigate) before re-running the migration.'
        );
    }
}
