<?php

use App\Exceptions\NullLeadIdApplicationsExistException;
use App\Models\Application;
use App\Models\Lead;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
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
