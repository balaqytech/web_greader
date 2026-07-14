<?php

use App\Exceptions\DuplicateCivilNumberSeasonException;
use App\Models\Application;
use Illuminate\Support\Facades\Schema;

const COMPOUND_INDEX = 'applications_student_civil_number_season_id_unique';

function reconciliationMigration(): object
{
    return require database_path('migrations/2026_07_13_100001_reconcile_applications_baseline_schema.php');
}

/**
 * @param  bool  $requireUnique  when true, only a UNIQUE index on the two columns counts
 */
function hasCompoundCivilSeasonIndex(bool $requireUnique = true): bool
{
    return collect(Schema::getIndexes('applications'))->contains(
        fn (array $index) => (! $requireUnique || ($index['unique'] ?? false))
            && count($index['columns']) === 2
            && in_array('student_civil_number', $index['columns'], true)
            && in_array('season_id', $index['columns'], true)
    );
}

function dropCompoundUniqueIndex(): void
{
    Schema::table('applications', fn ($table) => $table->dropUnique(COMPOUND_INDEX));
}

it('reconciles the additive baseline columns and compound unique index', function () {
    expect(Schema::hasColumn('applications', 'source'))->toBeTrue()
        ->and(Schema::hasColumn('applications', 'relationship_with_guardian'))->toBeTrue()
        ->and(Schema::hasColumn('applications', 'rejection_reason'))->toBeTrue()
        ->and(Schema::hasColumn('applications', 'student_id'))->toBeTrue()
        ->and(hasCompoundCivilSeasonIndex())->toBeTrue();
});

it('is idempotent and detects the true unique index when re-run', function () {
    // The unique index already exists (unique flag true) → detected → not re-added, no error.
    reconciliationMigration()->up();

    expect(hasCompoundCivilSeasonIndex())->toBeTrue()
        ->and(Schema::hasColumn('applications', 'student_id'))->toBeTrue();
});

it('re-adds drifted-away columns without error and preserves the existing index', function () {
    Schema::table('applications', function ($table) {
        $table->dropColumn(['source', 'relationship_with_guardian']);
    });

    expect(Schema::hasColumn('applications', 'source'))->toBeFalse();

    reconciliationMigration()->up();

    expect(Schema::hasColumn('applications', 'source'))->toBeTrue()
        ->and(Schema::hasColumn('applications', 'relationship_with_guardian'))->toBeTrue()
        ->and(hasCompoundCivilSeasonIndex())->toBeTrue();
});

it('fails with an actionable exception when duplicate non-null pairs exist', function () {
    dropCompoundUniqueIndex();

    $a = Application::factory()->create();
    $b = Application::factory()->create();
    $a->update(['student_civil_number' => 'DUP123', 'season_id' => $a->season_id]);
    $b->update(['student_civil_number' => 'DUP123', 'season_id' => $a->season_id]);

    expect(fn () => reconciliationMigration()->up())
        ->toThrow(DuplicateCivilNumberSeasonException::class);

    // Preflight ran before any DDL, so the index was not added.
    expect(hasCompoundCivilSeasonIndex())->toBeFalse();
});

it('ignores null civil numbers when checking for duplicates', function () {
    dropCompoundUniqueIndex();

    $a = Application::factory()->create();
    $b = Application::factory()->create();
    $a->update(['student_civil_number' => null, 'season_id' => $a->season_id]);
    $b->update(['student_civil_number' => null, 'season_id' => $a->season_id]);

    reconciliationMigration()->up();

    expect(hasCompoundCivilSeasonIndex())->toBeTrue();
});

it('does not mistake a same-column non-unique index for the required unique constraint', function () {
    dropCompoundUniqueIndex();

    Schema::table('applications', function ($table) {
        $table->index(['student_civil_number', 'season_id'], 'app_civil_season_nonunique');
    });

    // A non-unique compound index exists, but the unique constraint does not.
    expect(hasCompoundCivilSeasonIndex(requireUnique: true))->toBeFalse()
        ->and(hasCompoundCivilSeasonIndex(requireUnique: false))->toBeTrue();

    reconciliationMigration()->up();

    // The migration adds the genuine unique index alongside the non-unique one.
    expect(hasCompoundCivilSeasonIndex(requireUnique: true))->toBeTrue();
});

it('has a non-destructive rollback that drops nothing, including student_id', function () {
    reconciliationMigration()->down();

    expect(Schema::hasColumn('applications', 'student_id'))->toBeTrue()
        ->and(Schema::hasColumn('applications', 'source'))->toBeTrue()
        ->and(Schema::hasColumn('applications', 'relationship_with_guardian'))->toBeTrue()
        ->and(Schema::hasColumn('applications', 'rejection_reason'))->toBeTrue()
        ->and(hasCompoundCivilSeasonIndex())->toBeTrue();
});
