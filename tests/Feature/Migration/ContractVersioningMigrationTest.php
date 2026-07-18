<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\ApplicationContract;
use App\States\Applications\AwaitingContractSignature;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Physical-schema coverage for the contract-versioning migration (findings 3 & 4). The suite
 * runs on SQLite, whose transactional DDL is rolled back with each RefreshDatabase test, so
 * calling up()/down() here never leaks schema into other tests.
 */
function contractVersioningMigration(): object
{
    return require database_path('migrations/2026_07_18_090000_version_application_contracts_table.php');
}

function contractColumnIsNotNull(string $column): bool
{
    $info = collect(DB::select('PRAGMA table_info(application_contracts)'))
        ->firstWhere('name', $column);

    return $info !== null && (int) $info->notnull === 1;
}

function hasContractIndex(string $name): bool
{
    return collect(Schema::getIndexes('application_contracts'))
        ->contains(fn (array $index): bool => $index['name'] === $name);
}

function hasApplicationForeignKey(): bool
{
    return collect(Schema::getForeignKeys('application_contracts'))
        ->contains(fn (array $fk): bool => $fk['columns'] === ['application_id']);
}

/**
 * Insert a legacy (pre-versioning) contract row directly, as it would exist before this
 * migration ran. Requires the versioning columns to have been dropped first (see down()).
 */
function insertLegacyContract(Application $application, array $overrides = []): int
{
    return DB::table('application_contracts')->insertGetId(array_merge([
        'application_id' => $application->id,
        'token' => Str::random(64),
        'token_expires_at' => now()->addDays(7),
        'signed_at' => null,
        'signed_by_applicant' => false,
        'file_path' => null,
        'signature_path' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

// ── Finding 4: physical NOT NULL on SQLite ──────────────────────────────────

it('makes the backfilled columns physically NOT NULL', function (string $column) {
    expect(contractColumnIsNotNull($column))->toBeTrue();
})->with(['version', 'status', 'data_snapshot', 'rendered_body', 'template_hash']);

it('rejects a null insert into each NOT NULL column at the database level', function (string $column) {
    $contract = ApplicationContract::factory()->create();

    $row = (array) DB::table('application_contracts')->where('id', $contract->id)->first();
    unset($row['id']);
    $row['token'] = Str::random(64); // avoid tripping the token unique first
    $row[$column] = null;

    expect(fn () => DB::table('application_contracts')->insert($row))
        ->toThrow(QueryException::class);
})->with(['version', 'status', 'data_snapshot', 'rendered_body', 'template_hash']);

// ── Fresh schema shape ──────────────────────────────────────────────────────

it('has the versioned indexes and no standalone application_id unique after a fresh migration', function () {
    expect(hasContractIndex('application_contracts_application_id_version_unique'))->toBeTrue()
        ->and(hasContractIndex('application_contracts_application_id_status_index'))->toBeTrue()
        ->and(hasContractIndex('application_contracts_application_id_unique'))->toBeFalse()
        ->and(hasApplicationForeignKey())->toBeTrue()
        ->and(collect(Schema::getIndexes('application_contracts'))
            ->contains(fn (array $i) => $i['columns'] === ['token'] && ($i['unique'] ?? false)))->toBeTrue();
});

// ── Finding 3: rollback ordering ────────────────────────────────────────────

it('rolls back cleanly with zero contracts, restoring the standalone unique before dropping compounds', function () {
    contractVersioningMigration()->down();

    expect(Schema::hasColumn('application_contracts', 'version'))->toBeFalse()
        ->and(Schema::hasColumn('application_contracts', 'data_snapshot'))->toBeFalse()
        ->and(hasContractIndex('application_contracts_application_id_unique'))->toBeTrue()
        ->and(hasContractIndex('application_contracts_application_id_version_unique'))->toBeFalse()
        ->and(hasContractIndex('application_contracts_application_id_status_index'))->toBeFalse()
        ->and(hasApplicationForeignKey())->toBeTrue();
});

it('rolls back with one contract per application', function () {
    ApplicationContract::factory()->create();
    ApplicationContract::factory()->create();

    contractVersioningMigration()->down();

    expect(Schema::hasColumn('application_contracts', 'version'))->toBeFalse()
        ->and(hasContractIndex('application_contracts_application_id_unique'))->toBeTrue()
        ->and(hasApplicationForeignKey())->toBeTrue();
});

it('refuses to roll back when any application has multiple versions', function () {
    $application = Application::factory()->create();
    ApplicationContract::factory()->for($application)->create(['version' => 1]);
    ApplicationContract::factory()->for($application)->superseded()->create(['version' => 2]);

    expect(fn () => contractVersioningMigration()->down())
        ->toThrow(RuntimeException::class);

    // Nothing was torn down.
    expect(Schema::hasColumn('application_contracts', 'version'))->toBeTrue();
});

it('survives an up -> down -> up round trip', function () {
    $migration = contractVersioningMigration();

    $migration->down();
    $migration->up();

    expect(Schema::hasColumn('application_contracts', 'version'))->toBeTrue()
        ->and(contractColumnIsNotNull('version'))->toBeTrue()
        ->and(contractColumnIsNotNull('data_snapshot'))->toBeTrue()
        ->and(hasContractIndex('application_contracts_application_id_version_unique'))->toBeTrue()
        ->and(hasContractIndex('application_contracts_application_id_status_index'))->toBeTrue()
        ->and(hasContractIndex('application_contracts_application_id_unique'))->toBeFalse()
        ->and(hasApplicationForeignKey())->toBeTrue();
});

// ── §9.2 backfill ───────────────────────────────────────────────────────────

it('backfills signed, generated, and defensive superseded rows with conservative snapshots', function () {
    // Applications with no contract yet (plain factory does not create one), one per backfill
    // branch: a signed row, an awaiting-signature row, and a terminal orphan row.
    $signedApp = Application::factory()->create(['status' => 'awaiting_branch_review']);
    $generatedApp = Application::factory()->create(['status' => AwaitingContractSignature::$name]);
    $orphanApp = Application::factory()->create(['status' => 'accepted']);

    // Drop to the pre-versioning schema, then seed legacy rows exactly as they existed before.
    contractVersioningMigration()->down();

    $signedId = insertLegacyContract($signedApp, ['signed_at' => now(), 'signed_by_applicant' => true, 'file_path' => 'contracts/legacy-signed.pdf']);
    $generatedId = insertLegacyContract($generatedApp);
    $orphanId = insertLegacyContract($orphanApp);

    contractVersioningMigration()->up();

    $signed = DB::table('application_contracts')->find($signedId);
    $generated = DB::table('application_contracts')->find($generatedId);
    $orphan = DB::table('application_contracts')->find($orphanId);

    expect($signed->version)->toBe(1)
        ->and($signed->status)->toBe('signed')
        ->and($generated->status)->toBe('generated')
        ->and($orphan->status)->toBe('superseded')
        // The defensive superseded row has its token cleared.
        ->and($orphan->token)->toBeNull()
        // Backfilled snapshots are flagged so classification treats them conservatively.
        ->and(json_decode($signed->data_snapshot, true)['meta']['backfilled'])->toBeTrue()
        ->and(json_decode($generated->data_snapshot, true)['meta']['backfilled'])->toBeTrue();
});
