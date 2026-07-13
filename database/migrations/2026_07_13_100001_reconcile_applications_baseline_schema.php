<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarded, additive reconciliation of the `applications` table toward the target
 * baseline (§1.4 canonical reconciliation target). Every operation is guarded so it
 * is a safe no-op whether run against the repository/fresh schema (Tier 1) or a
 * drifted operational database (Tier 2) — it never raises a duplicate-column or
 * duplicate-index error on either path.
 *
 * Scope note: `lead_id` NOT NULL / restricted-FK tightening is intentionally NOT part
 * of this migration; it lands with transactional manual entry (Phase 0 commit 2).
 */
return new class extends Migration
{
    private const COMPOUND_UNIQUE_INDEX = 'applications_student_civil_number_season_id_unique';

    public function up(): void
    {
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
            // atomically on acceptance (§5.1). No column exists in any tier today.
            if (! Schema::hasColumn('applications', 'student_id')) {
                $table->foreignId('student_id')
                    ->nullable()
                    ->after('lead_id')
                    ->constrained()
                    ->nullOnDelete();
            }
        });

        // Compound unique index: absent in Tier 1, already present in Tier 2. Add only
        // where missing. On a real drifted/production database the §9.4 duplicate-pair
        // check must pass first; on fresh/empty installs there is nothing to conflict.
        if (! $this->hasCompoundUniqueIndex()) {
            Schema::table('applications', function (Blueprint $table) {
                $table->unique(['student_civil_number', 'season_id'], self::COMPOUND_UNIQUE_INDEX);
            });
        }
    }

    /**
     * Non-destructive rollback: only reverse the objects this migration is certain it
     * introduced on every tier (`student_id`). `source`, `relationship_with_guardian`,
     * `rejection_reason`, and the compound unique index may have predated this migration
     * on a drifted database, so they are deliberately left in place rather than dropped.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'student_id')) {
                $table->dropConstrainedForeignId('student_id');
            }
        });
    }

    private function hasCompoundUniqueIndex(): bool
    {
        foreach (Schema::getIndexes('applications') as $index) {
            $columns = $index['columns'] ?? [];

            if (in_array('student_civil_number', $columns, true)
                && in_array('season_id', $columns, true)
                && count($columns) === 2) {
                return true;
            }
        }

        return false;
    }
};
