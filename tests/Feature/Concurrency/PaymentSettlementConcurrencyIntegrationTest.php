<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\ProgramType;
use App\Enums\Source;
use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Season;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Leads\ContactedLead;
use App\States\Payments\Failed;
use App\States\Payments\Paid;
use App\States\Payments\Pending;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\CleanupAggregator;

/**
 * The generic `RealEngineRowLockTest` proves that `lockForUpdate()` is backed by a real
 * InnoDB lock. It deliberately does NOT prove that any specific transition is correct under
 * real concurrency — a single PHP process cannot interleave two blocking statements from one
 * thread, and simulating "concurrency" with two connections in one process cannot rule out a
 * bug that only manifests with genuinely independent execution (e.g. a static/shared PHP
 * state assumption).
 *
 * This test closes that gap for the one invariant that matters most in this domain: two
 * genuinely separate OS processes racing to resolve the *same* pending payment through the
 * real `ResolvePaymentFromProviderAction` — exactly the browser-return-vs-reconciliation race
 * production actually has — must produce exactly one Paid payment row and exactly one
 * application-advance activity, with the loser safely re-reading the winner's result rather
 * than crashing, inventing a second outcome, or double-advancing the application.
 *
 * Each worker is a genuine `php artisan payments:concurrency-worker` invocation (see
 * `PaymentConcurrencyWorkerCommand`) — a separate PHP process with its own memory, its own
 * database connection, and its own copy of every "singleton" — running the real
 * `ResolvePaymentFromProviderAction` against a fixed in-process gateway stand-in (no network
 * call, but every identity/amount/currency binding check and the stale-race catch are real).
 * `proc_open` starts both before either is awaited, so their execution genuinely overlaps at
 * the OS level.
 *
 * Safety, mirroring `RealEngineRowLockTest`:
 * - Own, dedicated `PAYMENT_CONCURRENCY_TEST_*` environment namespace; never inferred from the
 *   application's own `DB_*` variables.
 * - Strict opt-in parsing: unset/empty disables (skip); a handful of recognized values enable;
 *   anything else throws rather than silently skipping a typo.
 * - The target database name must end in `_test`; refused otherwise.
 * - This test *does* issue `CREATE DATABASE IF NOT EXISTS` for the dedicated `_test` schema
 *   (unlike the lock probe, which only ever touches a throwaway table) — provisioning a
 *   disposable schema and migrating the real application schema onto it is the only way to
 *   exercise the real settlement path end-to-end. It never touches any database whose name
 *   does not end in `_test`, and never issues `DROP DATABASE` on anything else.
 * - Tagged `integration`.
 *
 * The target engine here is whatever MySQL/MariaDB-compatible server the dedicated
 * `PAYMENT_CONCURRENCY_TEST_DB_*` variables point at; the server version is printed to stderr
 * so a run is traceable to a real, identifiable engine instance rather than asserted blindly.
 *
 * To opt in locally (provision the schema and a least-privilege user yourself — this test
 * will not create either):
 *   CREATE DATABASE greader_payment_concurrency_test;
 *   CREATE USER 'greader_pc_test'@'127.0.0.1' IDENTIFIED BY 'change-me';
 *   GRANT ALL PRIVILEGES ON greader_payment_concurrency_test.* TO 'greader_pc_test'@'127.0.0.1';
 * then:
 *   PAYMENT_CONCURRENCY_TEST_ENABLED=true PAYMENT_CONCURRENCY_TEST_DB_HOST=127.0.0.1 \
 *   PAYMENT_CONCURRENCY_TEST_DB_PORT=3306 \
 *   PAYMENT_CONCURRENCY_TEST_DB_DATABASE=greader_payment_concurrency_test \
 *   PAYMENT_CONCURRENCY_TEST_DB_USERNAME=greader_pc_test \
 *   PAYMENT_CONCURRENCY_TEST_DB_PASSWORD=change-me \
 *   vendor/bin/pest --compact --group=integration
 */
function parsePaymentConcurrencyOptIn(mixed $raw): bool
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
        'PAYMENT_CONCURRENCY_TEST_ENABLED=['.$raw.'] is not a recognized boolean value. Use one of: '
        .'true, false, 1, 0, yes, no, on, off (case-insensitive), or leave it unset to '
        .'disable this opt-in integration test.'
    );
}

function paymentConcurrencyOptedIn(): bool
{
    return parsePaymentConcurrencyOptIn(env('PAYMENT_CONCURRENCY_TEST_ENABLED'));
}

/**
 * @return array{host: string, port: string, database: string, username: string, password: string}
 */
function paymentConcurrencyConnectionConfig(): array
{
    $host = env('PAYMENT_CONCURRENCY_TEST_DB_HOST');
    $port = env('PAYMENT_CONCURRENCY_TEST_DB_PORT');
    $database = env('PAYMENT_CONCURRENCY_TEST_DB_DATABASE');
    $username = env('PAYMENT_CONCURRENCY_TEST_DB_USERNAME');
    $password = env('PAYMENT_CONCURRENCY_TEST_DB_PASSWORD', '');

    $missing = array_keys(array_filter([
        'PAYMENT_CONCURRENCY_TEST_DB_HOST' => blank($host),
        'PAYMENT_CONCURRENCY_TEST_DB_PORT' => blank($port),
        'PAYMENT_CONCURRENCY_TEST_DB_DATABASE' => blank($database),
        'PAYMENT_CONCURRENCY_TEST_DB_USERNAME' => blank($username),
    ]));

    if ($missing !== []) {
        throw new RuntimeException(
            'PAYMENT_CONCURRENCY_TEST_ENABLED=true but required variable(s) are missing: '.implode(', ', $missing).'. '
            .'Opting into this integration test requires every dedicated PAYMENT_CONCURRENCY_TEST_DB_* '
            .'variable to be set explicitly — it is never inferred from the application\'s own DB_* variables.'
        );
    }

    if (! str_ends_with($database, '_test')) {
        throw new RuntimeException(
            "PAYMENT_CONCURRENCY_TEST_DB_DATABASE [{$database}] does not end in `_test`. Refusing to run ".
            'against a database that is not unambiguously a dedicated test schema.'
        );
    }

    return compact('host', 'port', 'database', 'username', 'password');
}

it('disables the probe for unset and recognized false PAYMENT_CONCURRENCY_TEST_ENABLED values', function (mixed $raw) {
    expect(parsePaymentConcurrencyOptIn($raw))->toBeFalse();
})->with([
    'unset (null)' => [null],
    'empty string' => [''],
    'boolean false' => [false],
    '"false"' => ['false'],
    '"0"' => ['0'],
    '"no"' => ['no'],
    '"off"' => ['off'],
]);

it('enables the probe for recognized true PAYMENT_CONCURRENCY_TEST_ENABLED values', function (mixed $raw) {
    expect(parsePaymentConcurrencyOptIn($raw))->toBeTrue();
})->with([
    'boolean true' => [true],
    '"true"' => ['true'],
    '"1"' => ['1'],
    '"yes"' => ['yes'],
    '"on"' => ['on'],
]);

it('fails loudly on an unrecognized PAYMENT_CONCURRENCY_TEST_ENABLED value instead of silently disabling', function (string $raw) {
    expect(fn () => parsePaymentConcurrencyOptIn($raw))->toThrow(RuntimeException::class);
})->with([
    'truthy' => ['truthy'],
    'numeric but not 0/1' => ['2'],
    'whitespace-only' => ['   '],
]);

it('settles exactly one of two genuinely concurrent OS-process attempts on the same payment, and safely fails the loser', function () {
    if (! paymentConcurrencyOptedIn()) {
        test()->markTestSkipped(
            'PAYMENT_CONCURRENCY_TEST_ENABLED is not true. This opt-in integration test never provisions '
            .'or connects to any database by default; real cross-process settlement behavior remains '
            .'unverified in this run.'
        );
    }

    $config = paymentConcurrencyConnectionConfig();
    $connectionName = 'payment_concurrency_test';

    config(["database.connections.{$connectionName}_admin" => [
        'driver' => 'mysql',
        'host' => $config['host'],
        'port' => $config['port'],
        'database' => '',
        'username' => $config['username'],
        'password' => $config['password'],
        'charset' => 'utf8mb4',
    ]]);

    $databaseCreated = false;
    $probeFailure = null;
    $connection = null;

    try {
        DB::connection("{$connectionName}_admin")
            ->statement("CREATE DATABASE IF NOT EXISTS `{$config['database']}`");
        $databaseCreated = true;
        DB::purge("{$connectionName}_admin");

        config(["database.connections.{$connectionName}" => [
            'driver' => 'mysql',
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'],
            'charset' => 'utf8mb4',
        ]]);

        $connection = DB::connection($connectionName);
        $serverVersion = (string) $connection->selectOne('SELECT VERSION() AS version')->version;
        expect($serverVersion)->not->toBeEmpty();
        fwrite(STDERR, "\n[PaymentSettlementConcurrencyIntegrationTest] dedicated schema [{$config['database']}], server version: {$serverVersion}\n");

        Artisan::call('migrate', [
            '--database' => $connectionName,
            '--path' => 'database/migrations',
            '--force' => true,
        ]);

        $branch = Branch::on($connectionName)->create([
            'name' => 'Concurrency Test Branch',
            'address' => 'N/A',
            'phone' => '99000000',
            'mobile' => '99000000',
            'is_active' => true,
        ]);

        $season = Season::on($connectionName)->create([
            'name' => 'Concurrency Test Season',
            'type' => ProgramType::Academic,
            'start_date' => now()->subMonths(1)->toDateString(),
            'end_date' => now()->addMonths(5)->toDateString(),
            'is_active' => true,
        ]);

        $program = Program::on($connectionName)->create([
            'name' => 'Concurrency Test Program',
            'type' => ProgramType::Academic,
            'description' => 'N/A',
            'accept_installments' => false,
            'is_open' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $lead = Lead::on($connectionName)->create([
            'guardian_name' => 'Concurrency Guardian',
            'student_name' => 'Concurrency Student',
            'whatsapp' => '99000001',
            'program_type' => ProgramType::Academic,
            'source' => Source::DASHBOARD,
            'branch_id' => $branch->id,
            'season_id' => $season->id,
            'program_id' => $program->id,
            'status' => ContactedLead::$name,
        ]);

        $application = Application::on($connectionName)->create([
            'lead_id' => $lead->id,
            'season_id' => $season->id,
            'program_id' => $program->id,
            'branch_id' => $branch->id,
            'source' => Source::DASHBOARD,
            'status' => AwaitingRegistrationFee::$name,
            'student_name' => 'Concurrency Student',
            'student_gender' => Gender::Male,
            'student_birth_date' => now()->subYears(10)->toDateString(),
            'student_civil_number' => '10000001',
            'student_state' => 'N/A',
            'student_governorate' => 'N/A',
            'student_village' => 'N/A',
            'student_house_number' => '1',
            'student_parents_social_status' => 'N/A',
            'relationship_with_guardian' => GuardianRelationship::Father,
            'father_name' => 'Concurrency Father',
            'father_phone' => '99000002',
            'father_email' => 'father@example.test',
            'father_id_number' => '20000001',
            'father_occupation' => 'N/A',
            'father_is_guardian' => true,
            'mother_name' => 'Concurrency Mother',
            'mother_phone' => '99000003',
            'mother_id_number' => '30000001',
            'mother_is_guardian' => false,
        ]);

        $payment = Payment::on($connectionName)->create([
            'application_id' => $application->id,
            'branch_id' => $branch->id,
            'purpose' => PaymentPurpose::REGISTRATION_FEE,
            'method' => PaymentMethod::THAWANI,
            'status' => Pending::$name,
            'amount' => '25.000',
            'currency' => 'OMR',
        ]);

        $sessionId = 'sess_concurrency_'.bin2hex(random_bytes(8));

        $env = array_filter(
            array_merge($_SERVER, $_ENV, [
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $config['host'],
                'DB_PORT' => $config['port'],
                'DB_DATABASE' => $config['database'],
                'DB_USERNAME' => $config['username'],
                'DB_PASSWORD' => $config['password'],
            ]),
            fn (mixed $value): bool => is_scalar($value),
        );

        $command = [
            PHP_BINARY,
            base_path('artisan'),
            'payments:concurrency-worker',
            $payment->reference,
            $sessionId,
            '25.000',
            'OMR',
        ];

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        // Both processes are started before either is awaited, so their execution genuinely
        // overlaps — this is the load-bearing line of the whole test.
        $procA = proc_open($command, $descriptors, $pipesA, base_path(), $env);
        $procB = proc_open($command, $descriptors, $pipesB, base_path(), $env);

        if (! is_resource($procA) || ! is_resource($procB)) {
            throw new RuntimeException('Failed to spawn one or both worker processes.');
        }

        $outA = stream_get_contents($pipesA[1]);
        $errA = stream_get_contents($pipesA[2]);
        fclose($pipesA[1]);
        fclose($pipesA[2]);
        $exitA = proc_close($procA);

        $outB = stream_get_contents($pipesB[1]);
        $errB = stream_get_contents($pipesB[2]);
        fclose($pipesB[1]);
        fclose($pipesB[2]);
        $exitB = proc_close($procB);

        fwrite(STDERR, "[PaymentSettlementConcurrencyIntegrationTest] worker A: exit={$exitA} out=".trim((string) $outA).' err='.trim((string) $errA)."\n");
        fwrite(STDERR, "[PaymentSettlementConcurrencyIntegrationTest] worker B: exit={$exitB} out=".trim((string) $outB).' err='.trim((string) $errB)."\n");

        // Both processes race to resolve the *same* attempt — exactly the browser-return vs.
        // reconciliation race in production. The loser must not crash or invent a second
        // outcome: `ResolvePaymentFromProviderAction` catches the lock's StalePaymentStateException
        // and transparently returns the winner's already-settled result, so both processes are
        // expected to report the same successful "paid" outcome — never a 500, never a
        // TransitionNotFound, never a second Failed row.
        $results = [trim((string) $outA), trim((string) $outB)];
        $paidCount = count(array_filter($results, fn (string $r): bool => $r === 'RESULT:paid'));

        expect($exitA)->toBe(0)
            ->and($exitB)->toBe(0)
            ->and($paidCount)->toBe(2);

        $freshPayments = Payment::on($connectionName)
            ->where('application_id', $application->id)
            ->get();

        expect($freshPayments)->toHaveCount(1)
            ->and($freshPayments->first()->status)->toBeInstanceOf(Paid::class);

        $freshApplication = Application::on($connectionName)->find($application->id);
        expect($freshApplication->status)->toBeInstanceOf(AwaitingApplicationCompletion::class);

        $advanceActivityCount = ApplicationActivity::on($connectionName)
            ->where('application_id', $application->id)
            ->where('to_state', AwaitingApplicationCompletion::$name)
            ->count();

        expect($advanceActivityCount)->toBe(1);
    } catch (Throwable $exception) {
        $probeFailure = $exception;
    }

    $cleanup = new CleanupAggregator;

    $cleanup->run('drop dedicated schema', function () use ($databaseCreated, $connectionName, $config) {
        if ($databaseCreated) {
            DB::connection("{$connectionName}_admin")->statement("DROP DATABASE IF EXISTS `{$config['database']}`");
        }
    });

    $cleanup->run("purge {$connectionName} connection", fn () => DB::purge($connectionName));
    $cleanup->run("purge {$connectionName}_admin connection", fn () => DB::purge("{$connectionName}_admin"));

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
