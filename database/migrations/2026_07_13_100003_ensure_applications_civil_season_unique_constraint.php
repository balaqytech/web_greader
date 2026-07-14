<?php

use App\Exceptions\DuplicateCivilNumberSeasonException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forward repair migration (after 100002; 100001 is never edited again). It guarantees a
 * genuinely UNIQUE two-column constraint on (student_civil_number, season_id) exists, even
 * on databases where an earlier version of 100001 is already recorded as applied and may
 * have created the wrong shape (a non-unique index, a different-column index, or nothing).
 *
 * If the conventional index name is occupied by an index that is not the required unique
 * constraint, that existing index is preserved and the unique constraint is added under a
 * deterministic alternative name — the constraint is never silently skipped.
 */
return new class extends Migration
{
    private const CONVENTIONAL = 'applications_student_civil_number_season_id_unique';

    private const ALTERNATIVE = 'applications_student_civil_number_season_id_uidx';

    public function up(): void
    {
        // Already satisfied (under either name) — idempotent no-op.
        if ($this->hasGenuineUniqueCompoundIndex()) {
            return;
        }

        // Preflight before any DDL (MariaDB auto-commits DDL).
        $this->assertNoDuplicateCivilSeasonPairs();

        $name = $this->resolveIndexName();

        Schema::table('applications', function (Blueprint $table) use ($name) {
            $table->unique(['student_civil_number', 'season_id'], $name);
        });
    }

    /**
     * Non-destructive rollback: this migration only ever *adds* the required constraint
     * (never drops the pre-existing index it worked around), and cannot know on a drifted
     * database whether that constraint predated it, so it drops nothing.
     */
    public function down(): void
    {
        // Intentionally empty — see method docblock.
    }

    private function resolveIndexName(): string
    {
        if (! $this->indexNameTaken(self::CONVENTIONAL)) {
            return self::CONVENTIONAL;
        }

        if (! $this->indexNameTaken(self::ALTERNATIVE)) {
            return self::ALTERNATIVE;
        }

        throw new RuntimeException(
            'Both candidate names for the (student_civil_number, season_id) unique constraint are occupied; '
            .'manual intervention is required to add it.'
        );
    }

    private function hasGenuineUniqueCompoundIndex(): bool
    {
        foreach (Schema::getIndexes('applications') as $index) {
            $columns = $index['columns'] ?? [];

            if (($index['unique'] ?? false)
                && count($columns) === 2
                && in_array('student_civil_number', $columns, true)
                && in_array('season_id', $columns, true)) {
                return true;
            }
        }

        return false;
    }

    private function indexNameTaken(string $name): bool
    {
        foreach (Schema::getIndexes('applications') as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    private function assertNoDuplicateCivilSeasonPairs(): void
    {
        $duplicates = DB::table('applications')
            ->selectRaw('student_civil_number, season_id, COUNT(*) as aggregate')
            ->whereNotNull('student_civil_number')
            ->groupBy('student_civil_number', 'season_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        throw DuplicateCivilNumberSeasonException::fromDuplicates(
            $duplicates->map(fn ($row) => [
                'student_civil_number' => (string) $row->student_civil_number,
                'season_id' => $row->season_id,
                'count' => (int) $row->aggregate,
            ])->all()
        );
    }
};
