<?php

use App\Exceptions\NullLeadIdApplicationsExistException;
use App\Exceptions\OrphanedLeadIdApplicationsExistException;
use App\Models\Application;
use App\Models\Lead;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function leadIdMigration(): object
{
    return require database_path('migrations/2026_07_14_185315_tighten_applications_lead_id_not_null.php');
}

function leadIdColumnNullable(): bool
{
    return collect(Schema::getColumns('applications'))->firstWhere('name', 'lead_id')['nullable'];
}

function leadIdOnDelete(): ?string
{
    return collect(Schema::getForeignKeys('applications'))
        ->first(fn (array $fk) => $fk['columns'] === ['lead_id'])['on_delete'] ?? null;
}

function hasUniqueLeadIdIndex(): bool
{
    return collect(Schema::getIndexes('applications'))
        ->contains(fn (array $index) => ($index['unique'] ?? false) && ($index['columns'] ?? []) === ['lead_id']);
}

/**
 * RefreshDatabase already runs every migration, including this one, so the schema is
 * canonical (NOT NULL / RESTRICT) by the time any test starts — that would make a Tier 1
 * assertion a false positive if the migration were simply invoked again. This explicitly
 * drifts the schema back to the Tier 1 shape (nullable, SET NULL) first, mirroring the
 * historical migration exactly, so the reconcile path is genuinely exercised.
 */
function driftToTier1(): void
{
    $foreignKey = collect(Schema::getForeignKeys('applications'))
        ->first(fn (array $fk) => $fk['columns'] === ['lead_id']);

    Schema::table('applications', function (Blueprint $table) use ($foreignKey) {
        if ($foreignKey !== null) {
            $table->dropForeign($foreignKey['name'] ?? ['lead_id']);
        }

        $table->unsignedBigInteger('lead_id')->nullable()->change();

        $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
    });
}

/**
 * Simulates a prior local run of this migration that dropped the foreign key but was
 * interrupted before re-adding it — leaving the column with no lead_id FK at all, at either
 * nullability.
 */
function driftToNoFk(bool $nullable): void
{
    $foreignKey = collect(Schema::getForeignKeys('applications'))
        ->first(fn (array $fk) => $fk['columns'] === ['lead_id']);

    Schema::table('applications', function (Blueprint $table) use ($foreignKey, $nullable) {
        if ($foreignKey !== null) {
            $table->dropForeign($foreignKey['name'] ?? ['lead_id']);
        }

        $table->unsignedBigInteger('lead_id')->nullable($nullable)->change();
    });
}

/**
 * A lead_id foreign key that references the wrong table entirely — outside anything this
 * migration should ever attempt to drop or modify.
 */
function driftToWrongFkTarget(): void
{
    $foreignKey = collect(Schema::getForeignKeys('applications'))
        ->first(fn (array $fk) => $fk['columns'] === ['lead_id']);

    Schema::table('applications', function (Blueprint $table) use ($foreignKey) {
        if ($foreignKey !== null) {
            $table->dropForeign($foreignKey['name'] ?? ['lead_id']);
        }

        $table->unsignedBigInteger('lead_id')->nullable()->change();

        $table->foreign('lead_id')->references('id')->on('branches')->nullOnDelete();
    });
}

/**
 * A lead_id foreign key to leads.id that exists, but in a nullability/on-delete combination
 * that is neither the documented Tier 1 (nullable, SET NULL) nor a recoverable no-FK state.
 */
function driftToUnknownFkCombo(): void
{
    $foreignKey = collect(Schema::getForeignKeys('applications'))
        ->first(fn (array $fk) => $fk['columns'] === ['lead_id']);

    Schema::table('applications', function (Blueprint $table) use ($foreignKey) {
        if ($foreignKey !== null) {
            $table->dropForeign($foreignKey['name'] ?? ['lead_id']);
        }

        $table->unsignedBigInteger('lead_id')->nullable(false)->change();

        $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
    });
}

/**
 * leadIdForeignKeys() matches by *containment*: replaces the real getForeignKeys() result
 * with a fabricated one so a composite/extra-FK/unexpected-ON-UPDATE shape can be asserted
 * against without needing to physically construct an exotic constraint SQLite may not even
 * support creating. getColumns()/getIndexes() are left returning the real (canonical)
 * result, so only the foreign-key shape under test differs from a real database.
 */
function stubLeadIdForeignKeys(array $foreignKeys): void
{
    $realColumns = Schema::getColumns('applications');
    $realIndexes = Schema::getIndexes('applications');

    Schema::shouldReceive('getColumns')->with('applications')->andReturn($realColumns);
    Schema::shouldReceive('getIndexes')->with('applications')->andReturn($realIndexes);
    Schema::shouldReceive('getForeignKeys')->with('applications')->andReturn($foreignKeys);
}

it('starts canonical (NOT NULL, unique, RESTRICT) once RefreshDatabase has migrated', function () {
    expect(leadIdColumnNullable())->toBeFalse()
        ->and(leadIdOnDelete())->toBe('restrict')
        ->and(hasUniqueLeadIdIndex())->toBeTrue();
});

it('reconciles a Tier 1 (nullable, SET NULL) schema to the canonical NOT NULL/RESTRICT shape', function () {
    driftToTier1();

    expect(leadIdColumnNullable())->toBeTrue()
        ->and(leadIdOnDelete())->toBe('set null');

    leadIdMigration()->up();

    expect(leadIdColumnNullable())->toBeFalse()
        ->and(leadIdOnDelete())->toBe('restrict')
        ->and(hasUniqueLeadIdIndex())->toBeTrue();
});

it('is idempotent on an already-canonical (Tier 2) schema, even run repeatedly', function () {
    expect(leadIdColumnNullable())->toBeFalse();

    leadIdMigration()->up();
    leadIdMigration()->up();

    expect(leadIdColumnNullable())->toBeFalse()
        ->and(leadIdOnDelete())->toBe('restrict')
        ->and(hasUniqueLeadIdIndex())->toBeTrue()
        ->and(collect(Schema::getForeignKeys('applications'))->filter(fn (array $fk) => $fk['columns'] === ['lead_id']))->toHaveCount(1);
});

/**
 * SQLite's `.change()` compiles to several separate statements (create a __temp__ table,
 * copy rows into it, drop the original table, rename the temp table into place, recreate
 * indexes) — not one atomic operation. Without an explicit transaction, a failure between
 * the `drop table` and the rename would permanently destroy the original "applications"
 * table and its data. This forces exactly that failure — after the original table has
 * already been dropped, mid-rebuild — and proves the explicit `DB::transaction()` wrapping
 * reconcileViaBlueprint() rolls the whole rebuild back, leaving the original table, its
 * data, its (pre-reconciliation) FK shape, and its unique index completely intact.
 */
it('rolls back the entire SQLite table rebuild when a failure is forced after the original table is dropped', function () {
    driftToNoFk(nullable: true);

    $application = Application::factory()->create();
    $originalLeadId = $application->lead_id;

    // Each Pest test boots a fresh Application (and therefore a fresh event dispatcher), so
    // this listener never leaks into other tests. It must only fire once — the retried
    // migration call below performs the exact same "drop table" statement legitimately and
    // must be allowed to succeed.
    $hasFailedOnce = false;

    DB::listen(function ($query) use (&$hasFailedOnce): void {
        $sql = strtolower($query->sql);

        if (! $hasFailedOnce && str_contains($sql, 'drop table') && ! str_contains($sql, '__temp__')) {
            $hasFailedOnce = true;

            throw new RuntimeException('forced mid-rebuild failure');
        }
    });

    expect(fn () => leadIdMigration()->up())
        ->toThrow(RuntimeException::class, 'forced mid-rebuild failure');

    expect(Schema::hasTable('applications'))->toBeTrue()
        ->and(Schema::hasTable('__temp__applications'))->toBeFalse()
        ->and(leadIdColumnNullable())->toBeTrue()
        ->and(leadIdOnDelete())->toBeNull()
        ->and(hasUniqueLeadIdIndex())->toBeTrue()
        ->and(Application::count())->toBe(1)
        ->and(Application::find($application->id)->lead_id)->toBe($originalLeadId);

    // The rolled-back attempt left the schema exactly as reconcileViaBlueprint() found it,
    // so the migration remains runnable once retried.
    leadIdMigration()->up();

    expect(leadIdColumnNullable())->toBeFalse()
        ->and(leadIdOnDelete())->toBe('restrict')
        ->and(hasUniqueLeadIdIndex())->toBeTrue();
});

it('aborts before any DDL when NULL lead_id rows exist, leaving the schema shape intact', function () {
    driftToTier1();
    Application::factory()->create(['lead_id' => null]);

    expect(fn () => leadIdMigration()->up())
        ->toThrow(NullLeadIdApplicationsExistException::class);

    // The aborted attempt must not have touched the schema at all.
    expect(leadIdColumnNullable())->toBeTrue()
        ->and(leadIdOnDelete())->toBe('set null');
});

it('names the affected count and a sample of IDs in the abort exception', function () {
    driftToTier1();
    $orphan = Application::factory()->create(['lead_id' => null]);

    try {
        leadIdMigration()->up();
        expect(false)->toBeTrue('Expected the migration to throw.');
    } catch (NullLeadIdApplicationsExistException $exception) {
        expect($exception->getMessage())
            ->toContain('1 application(s)')
            ->toContain((string) $orphan->id);
    }
});

it('restricts deleting a lead once referenced by an application, after reconciliation', function () {
    driftToTier1();
    leadIdMigration()->up();

    $application = Application::factory()->create();
    $lead = Lead::find($application->lead_id);

    expect(fn () => $lead->delete())->toThrow(QueryException::class);
    expect(Lead::find($lead->id))->not->toBeNull();
});

it('keeps the unique lead_id constraint effective after reconciliation', function () {
    driftToTier1();
    leadIdMigration()->up();

    $first = Application::factory()->create();

    expect(fn () => Application::factory()->create(['lead_id' => $first->lead_id]))
        ->toThrow(QueryException::class);
});

it('recovers from a nullable, no-FK state left by a prior interrupted attempt', function () {
    driftToNoFk(nullable: true);

    expect(leadIdColumnNullable())->toBeTrue()
        ->and(leadIdOnDelete())->toBeNull();

    leadIdMigration()->up();

    expect(leadIdColumnNullable())->toBeFalse()
        ->and(leadIdOnDelete())->toBe('restrict')
        ->and(hasUniqueLeadIdIndex())->toBeTrue();
});

it('recovers from a NOT NULL, no-FK state left by a prior interrupted attempt', function () {
    driftToNoFk(nullable: false);

    expect(leadIdColumnNullable())->toBeFalse()
        ->and(leadIdOnDelete())->toBeNull();

    leadIdMigration()->up();

    expect(leadIdColumnNullable())->toBeFalse()
        ->and(leadIdOnDelete())->toBe('restrict')
        ->and(hasUniqueLeadIdIndex())->toBeTrue();
});

it('aborts before any DDL when orphaned (non-NULL, dangling) lead_id rows exist', function () {
    driftToNoFk(nullable: true);

    $application = Application::factory()->create();
    Lead::find($application->lead_id)->delete();

    expect(fn () => leadIdMigration()->up())
        ->toThrow(OrphanedLeadIdApplicationsExistException::class);

    // The aborted attempt must not have touched the schema at all.
    expect(leadIdColumnNullable())->toBeTrue()
        ->and(leadIdOnDelete())->toBeNull();
});

it('names the affected count and a sample of IDs in the orphan abort exception', function () {
    driftToNoFk(nullable: true);

    $application = Application::factory()->create();
    Lead::find($application->lead_id)->delete();

    try {
        leadIdMigration()->up();
        expect(false)->toBeTrue('Expected the migration to throw.');
    } catch (OrphanedLeadIdApplicationsExistException $exception) {
        expect($exception->getMessage())
            ->toContain('1 application(s)')
            ->toContain((string) $application->id);
    }
});

it('rejects a lead_id foreign key that targets something other than leads.id, before any DDL', function () {
    driftToWrongFkTarget();

    expect(leadIdOnDelete())->toBe('set null');

    expect(fn () => leadIdMigration()->up())->toThrow(RuntimeException::class);

    // Refused before touching anything.
    expect(leadIdOnDelete())->toBe('set null');
});

it('rejects a lead_id foreign key in a nullability/on-delete combination outside the documented tiers', function () {
    driftToUnknownFkCombo();

    expect(leadIdColumnNullable())->toBeFalse()
        ->and(leadIdOnDelete())->toBe('cascade');

    expect(fn () => leadIdMigration()->up())->toThrow(RuntimeException::class);

    // Refused before touching anything.
    expect(leadIdColumnNullable())->toBeFalse()
        ->and(leadIdOnDelete())->toBe('cascade');
});

it('rejects a composite foreign key that merely includes lead_id, before any DDL', function () {
    stubLeadIdForeignKeys([[
        'name' => 'applications_lead_id_branch_id_foreign',
        'columns' => ['lead_id', 'branch_id'],
        'foreign_schema' => 'main',
        'foreign_table' => 'leads',
        'foreign_columns' => ['id', 'branch_id'],
        'on_update' => 'no action',
        'on_delete' => 'restrict',
    ]]);

    expect(fn () => leadIdMigration()->up())->toThrow(RuntimeException::class);
});

it('rejects more than one relevant lead_id foreign key, before any DDL', function () {
    $tier1ToLeads = [
        'name' => 'applications_lead_id_foreign',
        'columns' => ['lead_id'],
        'foreign_schema' => 'main',
        'foreign_table' => 'leads',
        'foreign_columns' => ['id'],
        'on_update' => 'no action',
        'on_delete' => 'set null',
    ];

    stubLeadIdForeignKeys([$tier1ToLeads, [...$tier1ToLeads, 'name' => 'applications_lead_id_foreign_2']]);

    expect(fn () => leadIdMigration()->up())->toThrow(RuntimeException::class);
});

it('rejects a lead_id foreign key to leads.id with an unexpected ON UPDATE action rather than silently replacing it', function () {
    stubLeadIdForeignKeys([[
        'name' => 'applications_lead_id_foreign',
        'columns' => ['lead_id'],
        'foreign_schema' => 'main',
        'foreign_table' => 'leads',
        'foreign_columns' => ['id'],
        'on_update' => 'cascade',
        'on_delete' => 'set null',
    ]]);

    expect(fn () => leadIdMigration()->up())->toThrow(RuntimeException::class);
});

/**
 * reconcileMySql() emits MySQL-specific `ALTER TABLE ... MODIFY ...` syntax that SQLite
 * cannot parse, so invoking it directly against the sqlite test connection always throws —
 * deterministically reaching its catch block without needing a real MySQL/MariaDB
 * connection. That is exploited here purely to exercise the catch-and-recheck behavior: a
 * NULL lead_id row created *after* the normal preflight (simulating a write racing it) must
 * surface as the same actionable domain exception the preflight itself would have thrown.
 */
it('surfaces an actionable domain exception when a write races the MySQL reconciliation and inserts a NULL lead_id row', function () {
    driftToTier1();

    $migration = leadIdMigration();
    $reconcileMySql = new ReflectionMethod($migration, 'reconcileMySql');
    $reconcileMySql->setAccessible(true);

    Application::factory()->create(['lead_id' => null]);

    expect(fn () => $reconcileMySql->invoke($migration))
        ->toThrow(NullLeadIdApplicationsExistException::class);

    // The failed ALTER attempt never actually applied — schema remains exactly as
    // driftToTier1() left it.
    expect(leadIdColumnNullable())->toBeTrue()
        ->and(leadIdOnDelete())->toBe('set null');
});

it('rethrows the original database exception from a failed MySQL reconciliation when no race is found', function () {
    driftToTier1();

    $migration = leadIdMigration();
    $reconcileMySql = new ReflectionMethod($migration, 'reconcileMySql');
    $reconcileMySql->setAccessible(true);

    expect(fn () => $reconcileMySql->invoke($migration))
        ->toThrow(QueryException::class);
});

it('preserves schema shape after any deliberately failing reconciliation attempt', function () {
    driftToNoFk(nullable: true);
    $application = Application::factory()->create();
    Lead::find($application->lead_id)->delete();

    expect(fn () => leadIdMigration()->up())->toThrow(OrphanedLeadIdApplicationsExistException::class);

    // Schema is exactly as it was left by driftToNoFk(), and the migration remains runnable
    // once the orphan is resolved (proving the failed attempt left no half-applied state).
    expect(leadIdColumnNullable())->toBeTrue()
        ->and(leadIdOnDelete())->toBeNull()
        ->and(hasUniqueLeadIdIndex())->toBeTrue();

    $application->delete();
    leadIdMigration()->up();

    expect(leadIdColumnNullable())->toBeFalse()
        ->and(leadIdOnDelete())->toBe('restrict')
        ->and(hasUniqueLeadIdIndex())->toBeTrue();
});
