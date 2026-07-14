<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by the applications schema-reconciliation migration when it cannot add the
 * unique `(student_civil_number, season_id)` index because duplicate non-null pairs
 * already exist in the data. The message lists the offending pairs so an operator can
 * resolve them before re-running the migration.
 */
class DuplicateCivilNumberSeasonException extends RuntimeException
{
    /**
     * @param  array<int, array{student_civil_number: string, season_id: int|string, count: int}>  $duplicates
     */
    public static function fromDuplicates(array $duplicates): self
    {
        $lines = array_map(
            fn (array $d) => "  - civil_number={$d['student_civil_number']}, season_id={$d['season_id']} ({$d['count']} rows)",
            $duplicates,
        );

        return new self(
            'Cannot add the unique (student_civil_number, season_id) index: duplicate non-null pairs exist. '
            ."Resolve these before re-running the reconciliation migration:\n".implode("\n", $lines)
        );
    }
}
