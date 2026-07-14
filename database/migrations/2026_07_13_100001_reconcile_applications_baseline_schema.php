<?php

use App\Exceptions\DuplicateCivilNumberSeasonException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guarded, additive reconciliation of the `applications` table toward the target
 * baseline (§1.4 canonical reconciliation target). Every operation is guarded so it is a
 * safe no-op whether run against the repository/fresh schema (Tier 1) or a drifted
 * operational database (Tier 2) — it never raises a duplicate-column or duplicate-index
 * error on either path.
 *
 * The duplicate `(student_civil_number, season_id)` preflight runs *before* any DDL,
 * because MariaDB auto-commits each DDL statement and a mid-run failure could otherwise
 * leave the table partially reconciled.
 *
 * Scope note: `lead_id` NOT NULL / restricted-FK tightening is intentionally NOT part of
 * this migration; it lands with transactional manual entry (Phase 0 commit 2).
 */
return new class extends Migration
{
    private const COMPOUND_UNIQUE_INDEX = 'applications_student_civil_number_season_id_unique';

    public function up(): void
    {
        // Preflight BEFORE any DDL (see class docblock).
        if (! $this->hasCompoundUniqueIndex()) {
            $this->assertNoDuplicateCivilSeasonPairs();
        }

        Schema::table('applications', function (Blueprint $table) {
            // Present in Tier 1, absent in the drifted Tier 2 database. Add where missing
            // so the flat-schema forms/model can read/write them on every tier.
            if (! Schema::hasColumn('applications', 'source')) {
                $table->string('source')->default('website');
            }

            if (! Schema::hasColumn('applications', 'relationship_with_guardian')) {
                $table->string('relationship_with_guardian')->nullable();
            }

            // Absent in Tier 1, present in Tier 2. Add where missing so the reject flow
            // (§4.1) has its storage on every tier.
            if (! Schema::hasColumn('applications', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }

            // New on every tier: canonical Application -> Student link, populated
            // atomically on acceptance (§5.1).
            if (! Schema::hasColumn('applications', 'student_id')) {
                $table->foreignId('student_id')
                    ->nullable()
                    ->after('lead_id')
                    ->constrained()
                    ->nullOnDelete();
            }
        });

        // Compound unique index: absent in Tier 1, already present in Tier 2. Add only
        // when a genuine *unique* constraint is not already present and no index of the
        // target name exists — a same-column non-unique index must not be mistaken for it.
        if (! $this->hasCompoundUniqueIndex() && ! $this->hasIndexNamed(self::COMPOUND_UNIQUE_INDEX)) {
            Schema::table('applications', function (Blueprint $table) {
                $table->unique(['student_civil_number', 'season_id'], self::COMPOUND_UNIQUE_INDEX);
            });
        }
    }

    /**
     * Non-destructive rollback. Every object this migration manages is *conditionally*
     * added and the migration cannot determine whether a given object predated it on a
     * drifted database — so rollback intentionally drops nothing (including `student_id`)
     * rather than risk destroying pre-existing data/structure.
     */
    public function down(): void
    {
        // Intentionally empty — see method docblock.
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

    private function hasCompoundUniqueIndex(): bool
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

    private function hasIndexNamed(string $name): bool
    {
        foreach (Schema::getIndexes('applications') as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
