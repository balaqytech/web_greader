<?php

namespace App\Support\Database;

use Illuminate\Database\QueryException;

/**
 * Classifies whether a QueryException represents a genuine duplicate-key (unique
 * constraint) violation, as distinct from any other SQLSTATE 23000 integrity failure.
 *
 * MySQL/MariaDB report a duplicate-key violation unambiguously as driver error 1062
 * (ER_DUP_ENTRY) — foreign-key and NOT NULL violations use different driver codes there.
 * SQLite reports every constraint violation — UNIQUE, NOT NULL, CHECK, and FOREIGN KEY
 * alike — as the same driver code 19 (SQLITE_CONSTRAINT); the code alone cannot distinguish
 * them there, so the exception message is also required to confirm it really was a unique
 * constraint failure and not one of the others.
 */
final class DuplicateKeyViolation
{
    public static function detect(QueryException $exception): bool
    {
        if ((string) $exception->getCode() !== '23000') {
            return false;
        }

        $driverCode = $exception->errorInfo[1] ?? null;

        if ($driverCode === 1062) {
            return true;
        }

        if ($driverCode === 19) {
            return str_contains($exception->getMessage(), 'UNIQUE constraint failed');
        }

        return false;
    }
}
