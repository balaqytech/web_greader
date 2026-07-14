<?php

use App\Models\Guardian;
use App\Support\Database\DuplicateKeyViolation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * These trigger genuine SQLite constraint violations rather than fabricating QueryException
 * internals, since the whole point of DuplicateKeyViolation is to correctly read real driver
 * behavior — SQLite reports every constraint kind as the same driver code 19.
 */
function triggerQueryException(Closure $failingQuery): QueryException
{
    try {
        $failingQuery();
    } catch (QueryException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected a QueryException to be thrown.');
}

/**
 * Fabricates a QueryException using the real Laravel 13 API (not reflection into any
 * private/internal state), for driver behavior (MySQL/MariaDB 1062) that cannot be triggered
 * against the SQLite connection this test suite actually runs on.
 */
function fabricateQueryException(string $sqlState, int $driverCode, string $driverMessage): QueryException
{
    $previous = new PDOException(
        "SQLSTATE[{$sqlState}]: Integrity constraint violation: {$driverCode} {$driverMessage}",
        $sqlState,
    );
    $previous->errorInfo = [$sqlState, $driverCode, $driverMessage];

    return new QueryException(
        'mysql',
        'insert into `guardians` (`phone`) values (?)',
        ['some-value'],
        $previous,
    );
}

it('classifies a genuine SQLite UNIQUE constraint violation as a duplicate key', function () {
    Guardian::create(['name' => 'First', 'phone' => 'DUP-PHONE-1', 'id_number' => 'DUP-ID-1']);

    $exception = triggerQueryException(
        fn () => Guardian::create(['name' => 'Second', 'phone' => 'DUP-PHONE-1', 'id_number' => 'DUP-ID-2'])
    );

    expect((string) $exception->getCode())->toBe('23000')
        ->and($exception->errorInfo[1] ?? null)->toBe(19)
        ->and(DuplicateKeyViolation::detect($exception))->toBeTrue();
});

it('does not classify a SQLite NOT NULL constraint violation as a duplicate key', function () {
    // `name` is NOT NULL and has no default; omitting it violates NOT NULL, not UNIQUE, but
    // SQLite reports both under the same driver code 19 — the raw diagnostic disambiguates.
    $exception = triggerQueryException(fn () => DB::table('guardians')->insert([
        'phone' => 'NOT-NULL-PHONE',
        'id_number' => 'NOT-NULL-ID',
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    expect((string) $exception->getCode())->toBe('23000')
        ->and($exception->errorInfo[1] ?? null)->toBe(19)
        ->and($exception->errorInfo[2] ?? null)->not->toContain('UNIQUE constraint failed')
        ->and(DuplicateKeyViolation::detect($exception))->toBeFalse();
});

it('does not classify a SQLite FOREIGN KEY constraint violation as a duplicate key', function () {
    $exception = triggerQueryException(fn () => DB::table('students')->insert([
        'guardian_id' => 999999,
        'branch_id' => 999999,
        'name' => 'Orphan Student',
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    expect(DuplicateKeyViolation::detect($exception))->toBeFalse();
});

it('does not classify a non-integrity QueryException (unrelated SQLSTATE) as a duplicate key', function () {
    $exception = triggerQueryException(fn () => DB::statement('SELECT * FROM a_table_that_does_not_exist'));

    expect((string) $exception->getCode())->not->toBe('23000')
        ->and(DuplicateKeyViolation::detect($exception))->toBeFalse();
});

it('does not misclassify a non-unique SQLite violation whose bound value contains the literal UNIQUE constraint failed text', function () {
    // A NOT NULL violation on `name`, but `phone` (unrelated, not violated) is set to text
    // that would fool a check against QueryException::getMessage(), since that message embeds
    // the query's SQL with bound values substituted in.
    $exception = triggerQueryException(fn () => DB::table('guardians')->insert([
        'phone' => 'UNIQUE constraint failed: guardians.phone',
        'id_number' => 'MALICIOUS-BINDING-ID',
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    expect($exception->getMessage())->toContain('UNIQUE constraint failed')
        ->and($exception->errorInfo[2] ?? null)->not->toContain('UNIQUE constraint failed')
        ->and(DuplicateKeyViolation::detect($exception))->toBeFalse();
});

it('classifies a MySQL/MariaDB SQLSTATE 23000 driver error 1062 as a duplicate key', function () {
    $exception = fabricateQueryException('23000', 1062, "Duplicate entry 'RACE-PHONE' for key 'guardians.phone'");

    expect((string) $exception->getCode())->toBe('23000')
        ->and($exception->errorInfo[1])->toBe(1062)
        ->and(DuplicateKeyViolation::detect($exception))->toBeTrue();
});

it('classifies a MySQL/MariaDB driver error 1062 represented as a numeric string', function () {
    $previous = new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'x' for key 'guardians.phone'", '23000');
    $previous->errorInfo = ['23000', '1062', "Duplicate entry 'x' for key 'guardians.phone'"];

    $exception = new QueryException('mysql', 'insert into `guardians` (`phone`) values (?)', ['x'], $previous);

    expect(DuplicateKeyViolation::detect($exception))->toBeTrue();
});

it('does not classify a MySQL/MariaDB SQLSTATE 23000 that is not driver error 1062 as a duplicate key', function () {
    // 1048 (ER_BAD_NULL_ERROR): a NOT NULL violation, not a duplicate key.
    $exception = fabricateQueryException('23000', 1048, "Column 'name' cannot be null");

    expect((string) $exception->getCode())->toBe('23000')
        ->and($exception->errorInfo[1])->toBe(1048)
        ->and(DuplicateKeyViolation::detect($exception))->toBeFalse();
});
