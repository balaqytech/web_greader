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
    // SQLite reports both under the same driver code 19 — the message is what disambiguates.
    $exception = triggerQueryException(fn () => DB::table('guardians')->insert([
        'phone' => 'NOT-NULL-PHONE',
        'id_number' => 'NOT-NULL-ID',
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    expect((string) $exception->getCode())->toBe('23000')
        ->and($exception->errorInfo[1] ?? null)->toBe(19)
        ->and($exception->getMessage())->not->toContain('UNIQUE constraint failed')
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
