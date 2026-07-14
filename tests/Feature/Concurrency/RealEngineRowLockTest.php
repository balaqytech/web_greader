<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SQLite (the test suite's default connection) cannot prove that `lockForUpdate()` — used
 * throughout LockApplication and the acceptance/guardian/student race protection — produces
 * a genuine exclusive row lock: a single `:memory:` connection has nothing to contend with.
 * This test runs against a local MySQL/MariaDB-compatible server in a throwaway, isolated
 * database (created and dropped here; the operational `greader` database is never touched)
 * to prove the lock is real: a second, independent connection attempting the same
 * `SELECT ... FOR UPDATE` genuinely blocks until the first transaction ends.
 *
 * What this does NOT prove: a true circular-wait deadlock (SQLSTATE 40001 / error 1213)
 * requires two statements executing concurrently in opposite lock order across two
 * processes/threads; a single PHP test process executes synchronously and cannot interleave
 * two blocking statements to produce one. Bounded deadlock retry (`attempts: 3` passed to
 * every guarded `DB::transaction()` call in the locking chain) is therefore verified by
 * configuration and by Laravel's documented `causedByDeadlock()` handling, not by forcing a
 * real deadlock here. The local server also self-identifies as MySQL 8.4 rather than
 * MariaDB — InnoDB row-locking semantics are the same engine mechanism either way, but this
 * is not a literal MariaDB verification.
 */
function realEngineDsn(): array
{
    return [
        sprintf('mysql:host=%s;port=%s', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306')),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', ''),
    ];
}

function realEngineAvailable(): bool
{
    try {
        [$dsn, $user, $pass] = realEngineDsn();
        (new PDO($dsn, $user, $pass))->query('SELECT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

it('genuinely blocks a second connection on a locked row (real InnoDB engine, not SQLite)', function () {
    if (! realEngineAvailable()) {
        $this->markTestSkipped(
            'No local MySQL/MariaDB-compatible server reachable on 127.0.0.1:3306; '
            .'real row-lock/deadlock behavior remains unverified (SQLite cannot prove this).'
        );
    }

    $database = 'greader_phase0_lock_probe';
    [$dsn, $user, $pass] = realEngineDsn();
    $bootstrap = new PDO($dsn, $user, $pass);
    $bootstrap->exec("DROP DATABASE IF EXISTS `{$database}`");
    $bootstrap->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    config([
        'database.connections.lock_probe_a' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $database,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],
        'database.connections.lock_probe_b' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $database,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],
    ]);

    try {
        $connectionA = DB::connection('lock_probe_a');
        $connectionB = DB::connection('lock_probe_b');

        $connectionA->statement('CREATE TABLE lock_probe (id INT PRIMARY KEY, val INT NOT NULL) ENGINE=InnoDB');
        $connectionA->table('lock_probe')->insert(['id' => 1, 'val' => 0]);

        // Connection A locks the row and does not commit yet.
        $connectionA->beginTransaction();
        $connectionA->table('lock_probe')->where('id', 1)->lockForUpdate()->first();

        // Connection B must wait for A's lock; a short lock-wait timeout turns the wait into
        // a deterministic, fast assertion instead of a real hang.
        $connectionB->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blockedByRealLock = false;

        try {
            $connectionB->transaction(function () use ($connectionB) {
                $connectionB->table('lock_probe')->where('id', 1)->lockForUpdate()->first();
            });
        } catch (QueryException $exception) {
            $blockedByRealLock = str_contains($exception->getMessage(), 'Lock wait timeout')
                || str_contains($exception->getMessage(), '1205');
        }

        $connectionA->rollBack();

        expect($blockedByRealLock)->toBeTrue();

        // Once A releases the lock, B can now acquire it and proceed normally.
        $connectionB->transaction(function () use ($connectionB) {
            $connectionB->table('lock_probe')->where('id', 1)->lockForUpdate()->update(['val' => 1]);
        });

        expect($connectionA->table('lock_probe')->where('id', 1)->value('val'))->toBe(1);
    } finally {
        DB::purge('lock_probe_a');
        DB::purge('lock_probe_b');
        $bootstrap->exec("DROP DATABASE IF EXISTS `{$database}`");
    }
});
