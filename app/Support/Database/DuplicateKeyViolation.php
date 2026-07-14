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
 * them there, so the raw driver diagnostic (`errorInfo[2]`) is also required to confirm it
 * really was a unique constraint failure and not one of the others.
 *
 * `errorInfo[2]` — never `QueryException::getMessage()` — is inspected for that check
 * deliberately: Laravel's composed message additionally embeds the query's SQL with bound
 * values substituted in, so a bound value that merely happens to contain the literal text
 * "UNIQUE constraint failed" could otherwise cause a genuinely unrelated failure (e.g. a NOT
 * NULL violation, with that string as some other column's value) to be misclassified.
 * `errorInfo[2]` is the driver's own diagnostic text and is never influenced by bindings.
 */
final class DuplicateKeyViolation
{
    public static function detect(QueryException $exception): bool
    {
        if ((string) $exception->getCode() !== '23000') {
            return false;
        }

        $driverCode = self::normalizeDriverCode($exception->errorInfo[1] ?? null);

        if ($driverCode === 1062) {
            return true;
        }

        if ($driverCode === 19) {
            return str_contains((string) ($exception->errorInfo[2] ?? ''), 'UNIQUE constraint failed');
        }

        return false;
    }

    /**
     * PDO drivers are not guaranteed to represent the driver-specific error code the same
     * way; normalize both plain integers and numeric strings to an integer for comparison.
     */
    private static function normalizeDriverCode(mixed $code): ?int
    {
        if (is_int($code)) {
            return $code;
        }

        if (is_string($code) && is_numeric($code)) {
            return (int) $code;
        }

        return null;
    }
}
