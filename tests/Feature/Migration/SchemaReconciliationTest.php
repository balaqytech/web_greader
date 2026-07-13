<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function reconciliationMigration(): object
{
    return require database_path('migrations/2026_07_13_100001_reconcile_applications_baseline_schema.php');
}

function hasCompoundCivilSeasonIndex(): bool
{
    return collect(Schema::getIndexes('applications'))->contains(
        fn (array $index) => in_array('student_civil_number', $index['columns'], true)
            && in_array('season_id', $index['columns'], true)
            && count($index['columns']) === 2
    );
}

it('reconciles the additive baseline columns and compound unique index', function () {
    expect(Schema::hasColumn('applications', 'source'))->toBeTrue()
        ->and(Schema::hasColumn('applications', 'relationship_with_guardian'))->toBeTrue()
        ->and(Schema::hasColumn('applications', 'rejection_reason'))->toBeTrue()
        ->and(Schema::hasColumn('applications', 'student_id'))->toBeTrue()
        ->and(hasCompoundCivilSeasonIndex())->toBeTrue();
});

it('is idempotent when re-run against an already-reconciled schema', function () {
    // Re-running the guarded migration must not raise duplicate-column/index errors.
    reconciliationMigration()->up();

    expect(Schema::hasColumn('applications', 'student_id'))->toBeTrue()
        ->and(hasCompoundCivilSeasonIndex())->toBeTrue();
});

it('re-adds drifted-away columns without error and preserves the existing index', function () {
    Schema::table('applications', function ($table) {
        $table->dropColumn(['source', 'relationship_with_guardian']);
    });

    expect(Schema::hasColumn('applications', 'source'))->toBeFalse();

    // Simulates the drifted operational database (Tier 2): the compound index already
    // exists, so the migration must add only the missing columns without a duplicate error.
    reconciliationMigration()->up();

    expect(Schema::hasColumn('applications', 'source'))->toBeTrue()
        ->and(Schema::hasColumn('applications', 'relationship_with_guardian'))->toBeTrue()
        ->and(hasCompoundCivilSeasonIndex())->toBeTrue();
});
