<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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
 * - It requires explicit opt-in (`LOCK_TEST_ENABLED=true`) *and* a full set of dedicated
 *   `LOCK_TEST_DB_*` connection variables, entirely separate from the application's own
 *   `DB_*` variables. Neither is set by default (not in `.env`, not in `phpunit.xml`), so a
 *   normal `pest`/`artisan test` run never even attempts to connect anywhere but the default
 *   test connection — it skips immediately, before opening any socket.
 * - It additionally refuses to run unless the configured database name ends in `_test`, as a
 *   second, independent guard against accidentally pointing this at a real database.
 * - It is tagged `integration` so it can be selected or excluded explicitly
 *   (`--group=integration` / `--exclude-group=integration`).
 *
 * To opt in locally, provision a schema yourself (this test will not create one), e.g.:
 *   CREATE DATABASE greader_lock_test;
 * then run with:
 *   LOCK_TEST_ENABLED=true LOCK_TEST_DB_HOST=127.0.0.1 LOCK_TEST_DB_PORT=3306 \
 *   LOCK_TEST_DB_DATABASE=greader_lock_test LOCK_TEST_DB_USERNAME=root LOCK_TEST_DB_PASSWORD= \
 *   vendor/bin/pest --compact --group=integration
 *
 * What this does NOT prove: a true circular-wait deadlock (SQLSTATE 40001 / error 1213)
 * requires two statements executing concurrently in opposite lock order across two
 * processes/threads; a single PHP test process executes synchronously and cannot interleave
 * two blocking statements to produce one. Bounded deadlock retry (`attempts: 3` passed to
 * every guarded `DB::transaction()` call in the locking chain) is therefore verified by
 * configuration and by Laravel's documented `causedByDeadlock()` handling, not by forcing a
 * real deadlock here.
 */
function lockProbeConnectionConfig(): ?array
{
    if (! filter_var(env('LOCK_TEST_ENABLED', false), FILTER_VALIDATE_BOOL)) {
        return null;
    }

    $host = env('LOCK_TEST_DB_HOST');
    $port = env('LOCK_TEST_DB_PORT');
    $database = env('LOCK_TEST_DB_DATABASE');
    $username = env('LOCK_TEST_DB_USERNAME');
    $password = env('LOCK_TEST_DB_PASSWORD', '');

    if (blank($host) || blank($port) || blank($database) || blank($username)) {
        return null;
    }

    // A second, independent guard: refuse anything that isn't unambiguously a dedicated
    // test schema by name, regardless of what LOCK_TEST_DB_* happens to be set to.
    if (! str_ends_with($database, '_test')) {
        return null;
    }

    return compact('host', 'port', 'database', 'username', 'password');
}

it('genuinely blocks a second connection on a locked row (real InnoDB engine, not SQLite)', function () {
    $config = lockProbeConnectionConfig();

    if ($config === null) {
        $this->markTestSkipped(
            'Opt-in dedicated LOCK_TEST_DB_* env vars (and LOCK_TEST_ENABLED=true) are not configured, '
            .'or the configured database does not end in `_test`. This integration test never runs '
            .'against the application database by default; real row-lock behavior remains unverified '
            .'in this run (SQLite cannot prove it).'
        );
    }

    config([
        'database.connections.lock_probe_a' => array_merge($config, ['driver' => 'mysql', 'charset' => 'utf8mb4']),
        'database.connections.lock_probe_b' => array_merge($config, ['driver' => 'mysql', 'charset' => 'utf8mb4']),
    ]);

    $connectionA = DB::connection('lock_probe_a');
    $connectionB = DB::connection('lock_probe_b');

    $table = 'lock_probe_'.bin2hex(random_bytes(16));

    try {
        // Detect and report the server version so a run against this dedicated schema is
        // traceable to a real, identifiable engine instance.
        $serverVersion = (string) $connectionA->selectOne('SELECT VERSION() AS version')->version;
        expect($serverVersion)->not->toBeEmpty();
        fwrite(STDERR, "\n[RealEngineRowLockTest] dedicated schema [{$config['database']}], server version: {$serverVersion}\n");

        $connectionA->statement("CREATE TABLE `{$table}` (id INT PRIMARY KEY, val INT NOT NULL) ENGINE=InnoDB");

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
            $blockedByRealLock = str_contains($exception->getMessage(), 'Lock wait timeout')
                || str_contains($exception->getMessage(), '1205');
        }

        $connectionA->rollBack();

        expect($blockedByRealLock)->toBeTrue();

        // Once A releases the lock, B can now acquire it and proceed normally.
        $connectionB->transaction(function () use ($connectionB, $table) {
            $connectionB->table($table)->where('id', 1)->lockForUpdate()->update(['val' => 1]);
        });

        expect($connectionA->table($table)->where('id', 1)->value('val'))->toBe(1);
    } finally {
        // Only ever drop the single throwaway table this invocation created — never the
        // database itself.
        $connectionA->statement("DROP TABLE IF EXISTS `{$table}`");
        DB::purge('lock_probe_a');
        DB::purge('lock_probe_b');
    }
})->group('integration');
