<?php

use App\Exceptions\NullLeadIdApplicationsExistException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forward repair migration (after 100003; historical migrations are never edited). Reconciles
 * `applications.lead_id` to the canonical shape — unsigned, NOT NULL, unique, referencing
 * `leads.id` with `ON DELETE RESTRICT` — across both known schema tiers:
 *
 *   Tier 1 (fresh installs, per the historical migration): nullable, `ON DELETE SET NULL`.
 *   Tier 2 (the reviewer-configured operational database): already NOT NULL, already
 *   `ON DELETE RESTRICT` — the canonical target already, preserved here as a no-op.
 *
 * A column cannot be both NOT NULL and SET NULL on delete, so Tier 1 requires both the
 * nullability and the FK on-delete action to change together. The existing unique index on
 * `lead_id` (present on both tiers) is left untouched throughout — it is never dropped or
 * recreated. Foreign key names are discovered via schema inspection rather than assumed
 * (MySQL/MariaDB names them; SQLite does not name them at all, so drop-by-name would either
 * fail outright or — if it fell back to a guessed conventional name — could silently miss a
 * differently-named constraint on a drifted database).
 *
 * Now that CreateLeadWithApplicationAction guarantees every new application has a lead, this
 * is gated on a zero-`lead_id IS NULL` preflight (checked before any DDL) so it never silently
 * strands legacy NULL rows behind a constraint they cannot satisfy.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Already canonical (Tier 2, or a database this migration already reconciled) —
        // idempotent no-op.
        if ($this->isCanonical()) {
            return;
        }

        // Preflight before any DDL (MySQL/MariaDB DDL auto-commits and cannot be rolled back).
        $this->assertNoNullLeadIds();
        $this->assertKnownTierShape();

        $this->reconcile();
    }

    /**
     * Non-destructive rollback: this migration only ever moves a Tier 1 (nullable / SET NULL)
     * shape toward the canonical one, and preserves a Tier 2 (already NOT NULL / RESTRICT)
     * shape untouched. On a drifted database it cannot know whether NOT NULL / RESTRICT
     * predated this migration (Tier 2 always had it) or was created here (Tier 1), so it
     * cannot safely reverse either case and drops nothing.
     */
    public function down(): void
    {
        // Intentionally empty — see method docblock.
    }

    private function isCanonical(): bool
    {
        $column = collect(Schema::getColumns('applications'))->firstWhere('name', 'lead_id');

        if ($column === null || $column['nullable']) {
            return false;
        }

        $foreignKey = $this->leadIdForeignKey();

        if ($foreignKey === null
            || $foreignKey['foreign_table'] !== 'leads'
            || $foreignKey['foreign_columns'] !== ['id']
            || $foreignKey['on_delete'] !== 'restrict') {
            return false;
        }

        return $this->hasUniqueLeadIdIndex();
    }

    private function assertNoNullLeadIds(): void
    {
        $count = DB::table('applications')->whereNull('lead_id')->count();

        if ($count === 0) {
            return;
        }

        $sampleIds = DB::table('applications')
            ->whereNull('lead_id')
            ->orderBy('id')
            ->limit(10)
            ->pluck('id')
            ->all();

        throw NullLeadIdApplicationsExistException::make($count, $sampleIds);
    }

    /**
     * This migration only knows how to reconcile the two documented tiers. A database with a
     * NULL-lead_id-free but otherwise unrecognized shape (no unique index on lead_id at all)
     * is outside that scope — fail clearly rather than guess and risk building the wrong
     * constraint set.
     */
    private function assertKnownTierShape(): void
    {
        if (! $this->hasUniqueLeadIdIndex()) {
            throw new RuntimeException(
                'applications.lead_id has no unique index. This migration only reconciles the two '.
                'documented schema tiers (both of which already have this index); manual '.
                'intervention is required on this database.'
            );
        }
    }

    private function reconcile(): void
    {
        $foreignKey = $this->leadIdForeignKey();

        Schema::table('applications', function (Blueprint $table) use ($foreignKey) {
            if ($foreignKey !== null) {
                // MySQL/MariaDB name their foreign keys and require the exact name to drop
                // one; SQLite never names them, so dropForeign() must be given the column
                // instead (it matches by column when there is no name to match by).
                $table->dropForeign($foreignKey['name'] ?? ['lead_id']);
            }

            $table->unsignedBigInteger('lead_id')->nullable(false)->change();

            $table->foreign('lead_id')->references('id')->on('leads')->restrictOnDelete();
        });
    }

    /**
     * @return array{name: ?string, columns: list<string>, foreign_table: string, foreign_columns: list<string>, on_update: string, on_delete: string}|null
     */
    private function leadIdForeignKey(): ?array
    {
        return collect(Schema::getForeignKeys('applications'))
            ->first(fn (array $foreignKey) => $foreignKey['columns'] === ['lead_id']);
    }

    private function hasUniqueLeadIdIndex(): bool
    {
        return collect(Schema::getIndexes('applications'))
            ->contains(fn (array $index) => ($index['unique'] ?? false) && ($index['columns'] ?? []) === ['lead_id']);
    }
};
