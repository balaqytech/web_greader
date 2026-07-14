<?php

use App\Exceptions\DuplicateCivilNumberSeasonException;
use App\Models\Application;
use Illuminate\Support\Facades\Schema;

function repairMigration(): object
{
    return require database_path('migrations/2026_07_13_100003_ensure_applications_civil_season_unique_constraint.php');
}

function uniqueCivilSeasonIndexCount(): int
{
    return collect(Schema::getIndexes('applications'))->filter(
        fn (array $index) => ($index['unique'] ?? false)
            && count($index['columns']) === 2
            && in_array('student_civil_number', $index['columns'], true)
            && in_array('season_id', $index['columns'], true)
    )->count();
}

function applicationIndexNames(): array
{
    return collect(Schema::getIndexes('applications'))->pluck('name')->all();
}

function dropConventionalUniqueIndex(): void
{
    Schema::table('applications', fn ($table) => $table->dropUnique('applications_student_civil_number_season_id_unique'));
}

it('is a no-op on a fresh database that already has the genuine unique index', function () {
    expect(uniqueCivilSeasonIndexCount())->toBe(1);

    repairMigration()->up();

    expect(uniqueCivilSeasonIndexCount())->toBe(1);
});

it('is idempotent when run repeatedly', function () {
    repairMigration()->up();
    repairMigration()->up();

    expect(uniqueCivilSeasonIndexCount())->toBe(1);
});

it('repairs a database where the unique constraint is missing entirely', function () {
    dropConventionalUniqueIndex();
    expect(uniqueCivilSeasonIndexCount())->toBe(0);

    repairMigration()->up();

    expect(uniqueCivilSeasonIndexCount())->toBe(1)
        ->and(applicationIndexNames())->toContain('applications_student_civil_number_season_id_unique');
});

it('preserves a same-column non-unique index and adds the unique constraint under an alternative name', function () {
    dropConventionalUniqueIndex();
    Schema::table('applications', fn ($table) => $table->index(
        ['student_civil_number', 'season_id'],
        'applications_student_civil_number_season_id_unique',
    ));

    expect(uniqueCivilSeasonIndexCount())->toBe(0);

    repairMigration()->up();

    expect(uniqueCivilSeasonIndexCount())->toBe(1)
        ->and(applicationIndexNames())->toContain('applications_student_civil_number_season_id_unique')
        ->and(applicationIndexNames())->toContain('applications_student_civil_number_season_id_uidx');
});

it('preserves a different-column index occupying the conventional name and still adds the constraint', function () {
    dropConventionalUniqueIndex();
    Schema::table('applications', fn ($table) => $table->index(
        ['ref_no'],
        'applications_student_civil_number_season_id_unique',
    ));

    repairMigration()->up();

    expect(uniqueCivilSeasonIndexCount())->toBe(1)
        ->and(applicationIndexNames())->toContain('applications_student_civil_number_season_id_unique')
        ->and(applicationIndexNames())->toContain('applications_student_civil_number_season_id_uidx');
});

it('fails the duplicate preflight before adding the constraint', function () {
    dropConventionalUniqueIndex();

    $a = Application::factory()->create();
    $b = Application::factory()->create();
    $a->update(['student_civil_number' => 'DUP-REPAIR', 'season_id' => $a->season_id]);
    $b->update(['student_civil_number' => 'DUP-REPAIR', 'season_id' => $a->season_id]);

    expect(fn () => repairMigration()->up())
        ->toThrow(DuplicateCivilNumberSeasonException::class);

    expect(uniqueCivilSeasonIndexCount())->toBe(0);
});
