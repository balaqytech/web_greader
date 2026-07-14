<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\CleanupAggregator;

/**
 * SQLite (the test suite's default connection) cannot prove that `lockForUpdate()` — used
 * throughout LockApplication and the acceptance/guardian/student race protection — produces
 * a genuine exclusive row lock: a single `:memory:` connection has nothing to contend with.
 *
 * This is a generic, engine-level probe: it proves that InnoDB row locks genuinely block a
 * second connection on a throwaway table. It does NOT exercise any application transition,
 * model, or migration, and it does NOT prove that any specific acceptance/signing/guardian
 * code path is correct under concurrency — only that the underlying `lockForUpdate()`
 * primitive they all rely on is backed by a real lock on a real engine.
 *
 * Safety, by design:
 * - This test NEVER issues `CREATE DATABASE` / `DROP DATABASE`. It only ever creates and
 *   drops a single throwaway table, by a cryptographically random name, inside a database
 *   that must already exist and must already be dedicated to this purpose.
 * - Opt-in parsing is strict: unset/empty and a handful of recognized false values disable the
 *   probe (skip); a handful of recognized true values enable it; any other non-empty value is
 *   a misconfiguration and throws immediately, before any config or connection is touched —
 *   silently treating an unrecognized value as "disabled" would let a typo masquerade as
 *   "opted out" just as easily as silently skipping a misconfigured opt-in would.
 * - Once opted in, a missing dedicated `LOCK_TEST_DB_*` variable or a database name that does
 *   not end in `_test` also fails the test loudly (an exception, not a silent skip).
 * - Neither `LOCK_TEST_ENABLED` nor any `LOCK_TEST_DB_*` variable is set by default (not in
 *   `.env`, not in `phpunit.xml`), so a normal `pest`/`artisan test` run never even attempts
 *   to connect anywhere but the default test connection.
 * - It is tagged `integration` so it can be selected or excluded explicitly
 *   (`--group=integration` / `--exclude-group=integration`).
 *
 * To opt in locally, provision a schema and a *schema-scoped, least-privilege* user yourself
 * (this test will not create a database or a user) — grant only what the probe actually uses:
 * CREATE/DROP (the throwaway table), SELECT, INSERT, and UPDATE:
 *   CREATE DATABASE greader_lock_test;
 *   CREATE USER 'greader_lock_test'@'127.0.0.1' IDENTIFIED BY 'change-me';
 *   GRANT CREATE, DROP, SELECT, INSERT, UPDATE ON greader_lock_test.* TO 'greader_lock_test'@'127.0.0.1';
 * then run with:
 *   LOCK_TEST_ENABLED=true LOCK_TEST_DB_HOST=127.0.0.1 LOCK_TEST_DB_PORT=3306 \
 *   LOCK_TEST_DB_DATABASE=greader_lock_test LOCK_TEST_DB_USERNAME=greader_lock_test \
 *   LOCK_TEST_DB_PASSWORD=change-me vendor/bin/pest --compact --group=integration
 *
 * What this does NOT prove: a true circular-wait deadlock (SQLSTATE 40001 / error 1213)
 * requires two statements executing concurrently in opposite lock order across two
 * processes/threads; a single PHP test process executes synchronously and cannot interleave
 * two blocking statements to produce one. Bounded deadlock retry (`attempts: 3` passed to
 * every guarded `DB::transaction()` call in the locking chain) is therefore verified by
 * configuration and by Laravel's documented `causedByDeadlock()` handling, not by forcing a
 * real deadlock here.
 */

/**
 * Pure parsing, deliberately injectable so it can be tested directly with fabricated values
 * (including Laravel's own env()-level auto-casting of "true"/"false" to real booleans)
 * without mutating process environment state or connecting to anything.
 */
function parseLockProbeOptIn(mixed $raw): bool
{
    if ($raw === null || $raw === '') {
        return false;
    }

    if (is_bool($raw)) {
        return $raw;
    }

    $normalized = strtolower(trim((string) $raw));

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    throw new RuntimeException(
        'LOCK_TEST_ENABLED=['.$raw.'] is not a recognized boolean value. Use one of: '
        .'true, false, 1, 0, yes, no, on, off (case-insensitive), or leave it unset to '
        .'disable this opt-in integration test.'
    );
}

function lockProbeOptedIn(): bool
{
    return parseLockProbeOptIn(env('LOCK_TEST_ENABLED'));
}

/**
 * Only called once opted in — misconfiguration at that point must fail loudly, not skip.
 *
 * @return array{host: string, port: string, database: string, username: string, password: string}
 */
function lockProbeConnectionConfig(): array
{
    $host = env('LOCK_TEST_DB_HOST');
    $port = env('LOCK_TEST_DB_PORT');
    $database = env('LOCK_TEST_DB_DATABASE');
    $username = env('LOCK_TEST_DB_USERNAME');
    $password = env('LOCK_TEST_DB_PASSWORD', '');

    $missing = array_keys(array_filter([
        'LOCK_TEST_DB_HOST' => blank($host),
        'LOCK_TEST_DB_PORT' => blank($port),
        'LOCK_TEST_DB_DATABASE' => blank($database),
        'LOCK_TEST_DB_USERNAME' => blank($username),
    ]));

    if ($missing !== []) {
        throw new RuntimeException(
            'LOCK_TEST_ENABLED=true but required variable(s) are missing: '.implode(', ', $missing).'. '
            .'Opting into this integration test requires every dedicated LOCK_TEST_DB_* variable '
            .'to be set explicitly — it is never inferred from the application\'s own DB_* variables.'
        );
    }

    if (! str_ends_with($database, '_test')) {
        throw new RuntimeException(
            "LOCK_TEST_DB_DATABASE [{$database}] does not end in `_test`. Refusing to run against ".
            'a database that is not unambiguously a dedicated test schema.'
        );
    }

    return compact('host', 'port', 'database', 'username', 'password');
}

it('disables the probe for unset and recognized false LOCK_TEST_ENABLED values', function (mixed $raw) {
    expect(parseLockProbeOptIn($raw))->toBeFalse();
})->with([
    'unset (null)' => [null],
    'empty string' => [''],
    'boolean false' => [false],
    '"false"' => ['false'],
    '"FALSE"' => ['FALSE'],
    '"0"' => ['0'],
    '"no"' => ['no'],
    '"off"' => ['off'],
]);

it('enables the probe for recognized true LOCK_TEST_ENABLED values', function (mixed $raw) {
    expect(parseLockProbeOptIn($raw))->toBeTrue();
})->with([
    'boolean true' => [true],
    '"true"' => ['true'],
    '"TRUE"' => ['TRUE'],
    '"1"' => ['1'],
    '"yes"' => ['yes'],
    '"on"' => ['on'],
]);

it('fails loudly on an unrecognized LOCK_TEST_ENABLED value instead of silently disabling', function (string $raw) {
    expect(fn () => parseLockProbeOptIn($raw))->toThrow(RuntimeException::class);
})->with([
    'truthy' => ['truthy'],
    'numeric but not 0/1' => ['2'],
    'enable (typo)' => ['enable'],
    'whitespace-only' => ['   '],
]);

it('genuinely blocks a second connection on a locked row (real InnoDB engine, not SQLite)', function () {
    if (! lockProbeOptedIn()) {
        $this->markTestSkipped(
            'LOCK_TEST_ENABLED is not true. This opt-in integration test never runs against any '
            .'database by default; real row-lock behavior remains unverified in this run (SQLite '
            .'cannot prove it).'
        );
    }

    $config = lockProbeConnectionConfig();

    config([
        'database.connections.lock_probe_a' => array_merge($config, ['driver' => 'mysql', 'charset' => 'utf8mb4']),
        'database.connections.lock_probe_b' => array_merge($config, ['driver' => 'mysql', 'charset' => 'utf8mb4']),
    ]);

    $connectionA = DB::connection('lock_probe_a');
    $connectionB = DB::connection('lock_probe_b');

    $table = 'lock_probe_'.bin2hex(random_bytes(16));
    $tableCreated = false;
    $probeFailure = null;

    try {
        // Detect and report the server version so a run against this dedicated schema is
        // traceable to a real, identifiable engine instance.
        $serverVersion = (string) $connectionA->selectOne('SELECT VERSION() AS version')->version;
        expect($serverVersion)->not->toBeEmpty();
        fwrite(STDERR, "\n[RealEngineRowLockTest] dedicated schema [{$config['database']}], server version: {$serverVersion}\n");

        $connectionA->statement("CREATE TABLE `{$table}` (id INT PRIMARY KEY, val INT NOT NULL) ENGINE=InnoDB");
        $tableCreated = true;

        $engine = $connectionA->selectOne(
            'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$config['database'], $table],
        )->engine;
        expect($engine)->toBe('InnoDB');

        $connectionA->table($table)->insert(['id' => 1, 'val' => 0]);

        // Connection A locks the row and does not commit yet.
        $connectionA->beginTransaction();
        $connectionA->table($table)->where('id', 1)->lockForUpdate()->first();

        // Connection B must wait for A's lock; a short lock-wait timeout turns the wait into
        // a deterministic, fast assertion instead of a real hang.
        $connectionB->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blockedByRealLock = false;

        try {
            $connectionB->transaction(function () use ($connectionB, $table) {
                $connectionB->table($table)->where('id', 1)->lockForUpdate()->first();
            });
        } catch (QueryException $exception) {
            // 1205 (ER_LOCK_WAIT_TIMEOUT) is the only outcome this test expects here; anything
            // else is an unrelated failure and must not be silently reinterpreted as "blocked".
            if (($exception->errorInfo[1] ?? null) !== 1205) {
                throw $exception;
            }

            $blockedByRealLock = true;
        }

        $connectionA->rollBack();

        expect($blockedByRealLock)->toBeTrue();

        // Once A releases the lock, B can now acquire it and proceed normally.
        $connectionB->transaction(function () use ($connectionB, $table) {
            $connectionB->table($table)->where('id', 1)->lockForUpdate()->update(['val' => 1]);
        });

        expect($connectionA->table($table)->where('id', 1)->value('val'))->toBe(1);
    } catch (Throwable $exception) {
        $probeFailure = $exception;
    }

    // Every cleanup step is attempted regardless of whether an earlier one failed, and
    // regardless of whether the probe itself already failed above. Failures are collected,
    // not silently swallowed: connection purges still run in every case, and a cleanup
    // failure fails this test (after every cleanup attempt) rather than passing silently.
    $cleanup = new CleanupAggregator;

    $cleanup->run('rollback connection A transaction', function () use ($connectionA) {
        if ($connectionA->transactionLevel() > 0) {
            $connectionA->rollBack();
        }
    });

    $cleanup->run('drop probe table', function () use ($connectionA, $table, $tableCreated) {
        if ($tableCreated) {
            $connectionA->statement("DROP TABLE IF EXISTS `{$table}`");
        }
    });

    $cleanup->run('purge lock_probe_a connection', fn () => DB::purge('lock_probe_a'));
    $cleanup->run('purge lock_probe_b connection', fn () => DB::purge('lock_probe_b'));

    if ($probeFailure !== null) {
        if ($cleanup->hasErrors()) {
            $cleanupMessages = implode(' | ', array_map(fn (Throwable $e) => $e->getMessage(), $cleanup->errors()));

            throw new RuntimeException(
                'Probe failed: '.$probeFailure->getMessage()
                .' | Additionally, '.count($cleanup->errors()).' cleanup step(s) failed: '.$cleanupMessages,
                0,
                $probeFailure,
            );
        }

        throw $probeFailure;
    }

    $cleanup->throwIfAny();
})->group('integration');
