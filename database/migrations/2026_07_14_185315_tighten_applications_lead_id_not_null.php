<?php

use App\Exceptions\NullLeadIdApplicationsExistException;
use App\Exceptions\OrphanedLeadIdApplicationsExistException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forward repair migration (after 100003; historical migrations are never edited). Reconciles
 * `applications.lead_id` to the canonical shape — unsigned bigint, NOT NULL, unique,
 * referencing `leads.id` with `ON DELETE RESTRICT` — from any of the schema shapes this
 * migration recognizes:
 *
 *   Tier 1 (fresh installs, per the historical migration): nullable, `ON DELETE SET NULL`.
 *   Tier 2 (the reviewer-configured operational database, and the canonical target itself):
 *   already NOT NULL, already `ON DELETE RESTRICT` — preserved here as a no-op.
 *   Recoverable (a prior local run of *this* migration that dropped the foreign key but did
 *   not finish re-adding it): nullable or NOT NULL, with no lead_id foreign key at all.
 *
 * A column cannot be both NOT NULL and SET NULL on delete, so Tier 1 requires both the
 * nullability and the FK on-delete action to change together. The existing unique index on
 * `lead_id` (present on every recognized shape) is left untouched by this migration's own
 * statements throughout. On MySQL/MariaDB that means it is genuinely never dropped or
 * recreated. On SQLite, though, any `.change()` on a column forces Laravel's full-table
 * rebuild strategy, which drops and recreates the *entire* table (including that index) to
 * apply the change — so on SQLite the index is physically recreated as a byproduct; only the
 * *logical* uniqueness guarantee (not the physical index object) is preserved there. That
 * rebuild is several separate statements, not one atomic operation, and the SQLite schema
 * grammar does not advertise schema-transaction support to Laravel's migration runner — so it
 * is wrapped in an explicit `DB::transaction()` here rather than relying on the runner to
 * supply one.
 *
 * Foreign key names are discovered via schema inspection rather than assumed (MySQL/MariaDB
 * names them; SQLite does not name them at all, so drop-by-name would either fail outright or
 * — if it fell back to a guessed conventional name — could silently miss a differently-named
 * constraint on a drifted database). Foreign keys are matched by *containment* of lead_id in
 * their child-column list, not exact equality, so a composite foreign key that merely
 * includes lead_id is still found — and then refused, since this migration only ever
 * drops/re-adds a single-column constraint. A lead_id foreign key that exists but targets
 * anything other than `leads.id`, has an ON UPDATE action other than restrictive (see
 * isRestrictiveOnUpdateAction()), or more than one relevant foreign key, is refused before any
 * DDL runs rather than guessed at.
 *
 * "Restrictive" ON UPDATE is deliberately recognized as either schema-inspector value the
 * supported engines report for an unspecified/default ON UPDATE clause on this shape:
 * `no action` (SQLite, and MySQL as observed) or `restrict` (MariaDB 10.11, which reports its
 * own default restrictive behavior under that name rather than `no action`). Both reconcile
 * paths now emit an explicit `ON UPDATE RESTRICT` going forward, but the recognizer still
 * accepts either value so a database left in either state by an older engine/run remains
 * (and stays) canonical.
 *
 * Now that CreateLeadWithApplicationAction guarantees every new application has a lead, this
 * is gated on preflight checks (before any DDL — MySQL/MariaDB DDL auto-commits and cannot be
 * rolled back) for zero NULL lead_id rows and zero orphaned (non-NULL, but referencing a
 * deleted/nonexistent lead) rows, so it never silently strands rows behind a constraint they
 * cannot satisfy.
 *
 * On MySQL/MariaDB, the foreign key removal, the column type/nullability change, and the
 * foreign key re-addition are issued as a *single* `ALTER TABLE` statement. Each is otherwise
 * its own auto-committing DDL statement; combining them means the engine holds one metadata
 * lock for the whole change and can never leave the table observable in an intermediate
 * no-FK state if the process is interrupted mid-migration.
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
        $this->assertNoOrphanedLeadIds();
        $this->assertKnownRecoverableShape();

        if ($this->isMySqlFamily()) {
            $this->reconcileMySql();
        } elseif ($this->isSqlite()) {
            $this->reconcileViaBlueprint();
        } else {
            throw new RuntimeException(
                'This migration only reconciles mysql/mariadb and sqlite connections; the '.
                'current driver ('.DB::connection()->getDriverName().') is not supported. '.
                'Manual intervention is required on this database.'
            );
        }
    }

    /**
     * Non-destructive rollback: this migration only ever moves a Tier 1 or recoverable
     * (nullable/no-FK, NOT NULL/no-FK) shape toward the canonical one, and preserves an
     * already-canonical (Tier 2) shape untouched. On a drifted database it cannot know
     * whether NOT NULL/RESTRICT predated this migration (Tier 2 always had it) or was created
     * here, so it cannot safely reverse either case and drops nothing.
     */
    public function down(): void
    {
        // Intentionally empty — see method docblock.
    }

    private function isMySqlFamily(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    private function isCanonical(): bool
    {
        $column = $this->leadIdColumn();

        if ($column === null || $column['nullable'] || ! $this->isRecognizedLeadIdColumnType($column)) {
            return false;
        }

        $foreignKeys = $this->leadIdForeignKeys();

        if (count($foreignKeys) !== 1) {
            return false;
        }

        $foreignKey = $foreignKeys[0];

        if ($foreignKey['columns'] !== ['lead_id']
            || $foreignKey['foreign_table'] !== 'leads'
            || $foreignKey['foreign_columns'] !== ['id']
            || ! $this->isRestrictiveOnUpdateAction($foreignKey['on_update'])
            || $foreignKey['on_delete'] !== 'restrict') {
            return false;
        }

        return $this->hasUniqueLeadIdIndex();
    }

    /**
     * The single source of truth for what counts as "restrictive" ON UPDATE behavior across
     * this migration's supported engines — used identically by isCanonical() and
     * assertKnownRecoverableShape() so their definitions can never diverge. `no action` and
     * `restrict` are treated as equivalent here (see the class docblock for why); anything
     * else — `cascade`, `set null`, or any other/unknown value — is a behavior-changing
     * action and is never accepted.
     */
    private function isRestrictiveOnUpdateAction(string $onUpdate): bool
    {
        return in_array($onUpdate, ['no action', 'restrict'], true);
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
     * Guards against re-adding the foreign key onto rows left dangling by a prior interrupted
     * attempt (dropped the FK, never finished restoring it) — those would otherwise fail the
     * `ADD CONSTRAINT` with an opaque database error instead of this clear, actionable one.
     */
    private function assertNoOrphanedLeadIds(): void
    {
        $orphanedQuery = fn () => DB::table('applications')
            ->whereNotNull('lead_id')
            ->whereNotExists(
                fn ($query) => $query->select(DB::raw(1))
                    ->from('leads')
                    ->whereColumn('leads.id', 'applications.lead_id')
            );

        $count = $orphanedQuery()->count();

        if ($count === 0) {
            return;
        }

        $sampleIds = $orphanedQuery()->orderBy('id')->limit(10)->pluck('id')->all();

        throw OrphanedLeadIdApplicationsExistException::make($count, $sampleIds);
    }

    /**
     * This migration only knows how to reconcile the documented tiers and the recoverable
     * no-FK states. Anything else — no unique index at all, a composite foreign key that
     * merely includes `lead_id` among other columns, more than one relevant foreign key, a
     * lead_id foreign key that targets something other than `leads.id`, a non-restrictive
     * `ON UPDATE` action (see isRestrictiveOnUpdateAction()), or a nullability/on-delete
     * combination that is neither Tier 1 nor a recoverable no-FK state — is outside that
     * scope: fail clearly rather than guess and risk building the wrong constraint set.
     */
    private function assertKnownRecoverableShape(): void
    {
        if (! $this->hasUniqueLeadIdIndex()) {
            throw new RuntimeException(
                'applications.lead_id has no unique index. This migration only reconciles known '.
                'schema shapes (all of which already have this index); manual intervention is '.
                'required on this database.'
            );
        }

        $column = $this->leadIdColumn();

        if (! $this->isRecognizedLeadIdColumnType($column)) {
            throw new RuntimeException(
                "applications.lead_id has an unrecognized column type ({$column['type']}). This ".
                'migration only reconciles an integer-family lead_id column; manual intervention '.
                'is required on this database.'
            );
        }

        // leadIdForeignKeys() matches by *containment* (any FK whose child-column list
        // includes lead_id), so a composite FK shows up here too. This migration only ever
        // drops/re-adds a single-column lead_id constraint; a composite one is refused before
        // it is mistaken for — and silently dropped as — a Tier 1/recoverable single-column
        // FK.
        $foreignKeys = $this->leadIdForeignKeys();

        $compositeForeignKey = collect($foreignKeys)->first(fn (array $fk) => count($fk['columns']) > 1);

        if ($compositeForeignKey !== null) {
            throw new RuntimeException(
                'applications.lead_id is part of a composite foreign key (columns: '.
                implode(', ', $compositeForeignKey['columns']).'). This migration only reconciles a '.
                'single-column lead_id foreign key; manual intervention is required on this database.'
            );
        }

        if (count($foreignKeys) > 1) {
            throw new RuntimeException(
                'applications.lead_id has '.count($foreignKeys).' foreign keys. This migration only '.
                'reconciles a single, unambiguous lead_id foreign key; manual intervention is '.
                'required on this database.'
            );
        }

        if ($foreignKeys === []) {
            // Recoverable no-FK state (nullable or NOT NULL) — accepted regardless of
            // nullability now that the orphan preflight above has already passed.
            return;
        }

        $foreignKey = $foreignKeys[0];

        if ($foreignKey['foreign_table'] !== 'leads' || $foreignKey['foreign_columns'] !== ['id']) {
            $target = $foreignKey['foreign_table'].'.'.implode(',', $foreignKey['foreign_columns']);

            throw new RuntimeException(
                'applications.lead_id has a foreign key that does not reference leads.id (references '.
                "{$target}). Refusing to drop or modify an unrecognized constraint; manual ".
                'intervention is required on this database.'
            );
        }

        if (! $this->isRestrictiveOnUpdateAction($foreignKey['on_update'])) {
            throw new RuntimeException(
                'applications.lead_id has a foreign key to leads.id with a non-restrictive ON UPDATE '.
                "action ({$foreignKey['on_update']}). Refusing to silently replace an unexpected ON ".
                'UPDATE behavior; manual intervention is required on this database.'
            );
        }

        $isTier1 = $column['nullable'] && $foreignKey['on_delete'] === 'set null';

        if (! $isTier1) {
            throw new RuntimeException(
                'applications.lead_id has a foreign key to leads.id in an unrecognized '.
                "combination (nullable: {$this->boolLabel($column['nullable'])}, ".
                "on_delete: {$foreignKey['on_delete']}). This migration only reconciles the ".
                'documented Tier 1 (nullable, SET NULL) shape; manual intervention is required '.
                'on this database.'
            );
        }
    }

    private function boolLabel(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * A single `ALTER TABLE` statement: the foreign key drop (if one exists), the column
     * type/nullability change, and the foreign key re-add all happen under one metadata lock.
     * Built and executed as raw SQL rather than through separate Blueprint commands, which
     * would otherwise compile to separate auto-committing statements.
     *
     * If the statement itself fails, the table is left exactly as it was (MySQL/MariaDB do
     * not apply a failed `ALTER TABLE` at all). The one case this can happen despite the
     * preflight passing is a write racing the preflight — a NULL or orphaned lead_id row
     * inserted between the checks above and this statement. The same checks are re-run here
     * so that race surfaces as the same actionable domain exception a slower race would have
     * hit at preflight, rather than an opaque database error; if they find nothing, the
     * original database exception is the real cause and is rethrown unchanged.
     */
    private function reconcileMySql(): void
    {
        $foreignKeys = $this->leadIdForeignKeys();
        $existingName = $foreignKeys[0]['name'] ?? null;

        $clauses = [];

        if ($existingName !== null) {
            $clauses[] = 'DROP FOREIGN KEY '.$this->quoteIdentifier($existingName);
        }

        $clauses[] = 'MODIFY '.$this->quoteIdentifier('lead_id').' BIGINT UNSIGNED NOT NULL';

        $clauses[] = 'ADD CONSTRAINT '.$this->quoteIdentifier($this->newForeignKeyName($existingName))
            .' FOREIGN KEY ('.$this->quoteIdentifier('lead_id').') '
            .'REFERENCES '.$this->quoteIdentifier('leads').' ('.$this->quoteIdentifier('id').') '
            .'ON DELETE RESTRICT ON UPDATE RESTRICT';

        try {
            DB::statement('ALTER TABLE '.$this->quoteIdentifier('applications').' '.implode(', ', $clauses));
        } catch (Throwable $e) {
            $this->assertNoNullLeadIds();
            $this->assertNoOrphanedLeadIds();

            throw $e;
        }
    }

    /**
     * MySQL/MariaDB refuse `DROP FOREIGN KEY x, ADD CONSTRAINT x ...` in the same `ALTER
     * TABLE` statement — "Duplicate foreign key constraint name" — even though the drop
     * logically happens first. The Laravel-conventional name is used whenever it would not
     * collide with the name being dropped in this same statement; otherwise a distinct,
     * still-recognizable alternate is used. The constraint's *name* is not part of the
     * canonical shape this migration checks for (only its column/target/on-delete-action/
     * uniqueness are), so this never affects idempotency.
     */
    private function newForeignKeyName(?string $existingName): string
    {
        $default = 'applications_lead_id_foreign';

        return $existingName === $default ? $default.'_restrict' : $default;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    /**
     * SQLite has no `ALTER TABLE ... MODIFY` / multi-clause equivalent; Laravel's Blueprint
     * compiles a column `.change()` there into a full-table rebuild (create a new table,
     * copy the rows across, drop the old table, rename the new one) — several separate
     * statements, not one atomic operation. Laravel's own migration runner only wraps a
     * migration in a database transaction when the schema grammar's
     * `supportsSchemaTransactions()` reports true, and the SQLite grammar does not report
     * that — so nothing wraps this rebuild in a transaction automatically. It is wrapped
     * here, explicitly, so a failure partway through the rebuild (e.g. mid-copy) rolls back
     * to the original table intact rather than leaving the database in whatever
     * intermediate state the rebuild had reached.
     */
    private function reconcileViaBlueprint(): void
    {
        $foreignKeys = $this->leadIdForeignKeys();

        DB::transaction(function () use ($foreignKeys) {
            Schema::table('applications', function (Blueprint $table) use ($foreignKeys) {
                if ($foreignKeys !== []) {
                    // MySQL/MariaDB name their foreign keys and require the exact name to
                    // drop one; SQLite never names them, so dropForeign() must be given the
                    // column instead (it matches by column when there is no name to match
                    // by).
                    $table->dropForeign($foreignKeys[0]['name'] ?? ['lead_id']);
                }

                $table->unsignedBigInteger('lead_id')->nullable(false)->change();

                $table->foreign('lead_id')->references('id')->on('leads')->restrictOnDelete()->restrictOnUpdate();
            });
        });
    }

    private function isRecognizedLeadIdColumnType(?array $column): bool
    {
        if ($column === null) {
            return false;
        }

        if ($this->isMySqlFamily()) {
            return $column['type_name'] === 'bigint' && str_contains($column['type'], 'unsigned');
        }

        // SQLite has no distinct unsigned/bigint storage class; every integer variant
        // normalizes to the same "integer" type_name, so that is the closest verifiable
        // equivalent there.
        return $column['type_name'] === 'integer';
    }

    /**
     * @return array{name: ?string, columns: list<string>, foreign_table: string, foreign_columns: list<string>, on_update: string, on_delete: string}
     */
    private function leadIdColumn(): ?array
    {
        return collect(Schema::getColumns('applications'))->firstWhere('name', 'lead_id');
    }

    /**
     * Matches by *containment*, not exact equality — a composite foreign key whose
     * child-column list merely includes `lead_id` alongside other columns must still be
     * found here (and then refused by assertKnownRecoverableShape()) rather than silently
     * treated as if lead_id had no foreign key at all.
     *
     * @return list<array{name: ?string, columns: list<string>, foreign_table: string, foreign_columns: list<string>, on_update: string, on_delete: string}>
     */
    private function leadIdForeignKeys(): array
    {
        return collect(Schema::getForeignKeys('applications'))
            ->filter(fn (array $foreignKey) => in_array('lead_id', $foreignKey['columns'], true))
            ->values()
            ->all();
    }

    private function hasUniqueLeadIdIndex(): bool
    {
        return collect(Schema::getIndexes('applications'))
            ->contains(fn (array $index) => ($index['unique'] ?? false) && ($index['columns'] ?? []) === ['lead_id']);
    }
};
