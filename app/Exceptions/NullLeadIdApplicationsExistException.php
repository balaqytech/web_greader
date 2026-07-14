<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by the lead_id-tightening migration when it cannot add the NOT NULL constraint
 * because applications with a NULL lead_id already exist. Lists the count and a small
 * sample of affected IDs so an operator can resolve them (assign a lead, or investigate)
 * before re-running the migration.
 */
class NullLeadIdApplicationsExistException extends RuntimeException
{
    /**
     * @param  array<int, int|string>  $sampleIds
     */
    public static function make(int $count, array $sampleIds): self
    {
        return new self(
            "Cannot make applications.lead_id NOT NULL: {$count} application(s) have a NULL lead_id. "
            .'Sample affected IDs: '.implode(', ', $sampleIds).'. '
            .'Resolve these (assign a lead or investigate) before re-running the migration.'
        );
    }
}
