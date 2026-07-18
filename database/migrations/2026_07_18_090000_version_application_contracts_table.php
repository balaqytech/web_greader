<?php

declare(strict_types=1);

use App\Actions\Contracts\BuildContractSnapshotAction;
use App\Models\Application;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converts `application_contracts` from one row per application to immutable versions (§5.5,
 * §9.2). Every step is guarded (`hasColumn`/`hasIndex`) so the migration is a no-op on a tier
 * that already matches and never raises a duplicate-column or duplicate-index error.
 *
 * Ordering matters on MySQL: the compound `(application_id, ...)` indexes are created *before*
 * the standalone `application_id` unique is dropped, so the foreign key on `application_id`
 * always has a covering index and the drop cannot fail with "needed in a foreign key
 * constraint". On a fresh install the table is empty, the backfill loop does nothing, and the
 * new columns are simply added.
 *
 * Backfill derives each existing row as version 1: `signed` when a signature exists, `generated`
 * only while its application still awaits signature, otherwise `superseded` (a defensive default
 * for orphans, expected to be empty). Superseded rows have their tokens cleared. The snapshot,
 * body, and hash are a best-effort reconstruction flagged `meta.backfilled = true`, so
 * correction classification treats any diff against a pre-migration version conservatively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('application_contracts', 'version')) {
                $table->unsignedInteger('version')->nullable()->after('application_id');
            }

            if (! Schema::hasColumn('application_contracts', 'status')) {
                $table->string('status')->nullable()->after('version');
            }

            if (! Schema::hasColumn('application_contracts', 'data_snapshot')) {
                $table->json('data_snapshot')->nullable()->after('status');
            }

            if (! Schema::hasColumn('application_contracts', 'rendered_body')) {
                $table->longText('rendered_body')->nullable()->after('data_snapshot');
            }

            if (! Schema::hasColumn('application_contracts', 'template_hash')) {
                $table->string('template_hash')->nullable()->after('rendered_body');
            }

            if (! Schema::hasColumn('application_contracts', 'generated_by')) {
                $table->foreignId('generated_by')->nullable()->after('template_hash')
                    ->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('application_contracts', 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable()->after('signature_path');
            }

            if (! Schema::hasColumn('application_contracts', 'superseded_by_contract_id')) {
                $table->foreignId('superseded_by_contract_id')->nullable()->after('superseded_at')
                    ->constrained('application_contracts')->nullOnDelete();
            }
        });

        $this->backfillVersions();

        Schema::table('application_contracts', function (Blueprint $table) {
            if (! $this->hasIndex('application_contracts', 'application_contracts_application_id_status_index')) {
                $table->index(['application_id', 'status'], 'application_contracts_application_id_status_index');
            }

            if (! $this->hasIndex('application_contracts', 'application_contracts_application_id_version_unique')) {
                $table->unique(['application_id', 'version'], 'application_contracts_application_id_version_unique');
            }
        });

        // Only now — with covering compound indexes in place — is it safe to drop the
        // standalone unique the FK previously leaned on.
        Schema::table('application_contracts', function (Blueprint $table) {
            if ($this->hasIndex('application_contracts', 'application_contracts_application_id_unique')) {
                $table->dropUnique('application_contracts_application_id_unique');
            }
        });

        $this->enforceNotNull();
    }

    public function down(): void
    {
        $multiVersioned = DB::table('application_contracts')
            ->select('application_id')
            ->groupBy('application_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($multiVersioned) {
            throw new RuntimeException(
                'Refusing to reverse contract versioning: at least one application already has '.
                'multiple contract versions. Rolling back would discard signed/historical versions. '.
                'Resolve the extra versions manually before reverting.'
            );
        }

        // Restore the standalone application_id unique FIRST, so the foreign key on
        // application_id always retains a covering index before either compound index is
        // dropped. Dropping the compound indexes first fails on MySQL/MariaDB with SQLSTATE
        // HY000/1553 ("needed in a foreign key constraint"). Safe to add here because the guard
        // above already refused when any application has more than one version, so application_id
        // is unique across the surviving rows. Never disables foreign-key checks.
        if (! $this->hasIndex('application_contracts', 'application_contracts_application_id_unique')) {
            Schema::table('application_contracts', function (Blueprint $table) {
                $table->unique('application_id', 'application_contracts_application_id_unique');
            });
        }

        Schema::table('application_contracts', function (Blueprint $table) {
            if ($this->hasIndex('application_contracts', 'application_contracts_application_id_version_unique')) {
                $table->dropUnique('application_contracts_application_id_version_unique');
            }

            if ($this->hasIndex('application_contracts', 'application_contracts_application_id_status_index')) {
                $table->dropIndex('application_contracts_application_id_status_index');
            }
        });

        Schema::table('application_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('application_contracts', 'superseded_by_contract_id')) {
                $table->dropConstrainedForeignId('superseded_by_contract_id');
            }

            if (Schema::hasColumn('application_contracts', 'generated_by')) {
                $table->dropConstrainedForeignId('generated_by');
            }

            foreach (['version', 'status', 'data_snapshot', 'rendered_body', 'template_hash', 'superseded_at'] as $column) {
                if (Schema::hasColumn('application_contracts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Best-effort reconstruction of a version-1 snapshot for each pre-existing contract row.
     * Runs per row through the authoritative snapshot builder so backfilled bodies/hashes match
     * the live format; on any failure it falls back to an empty, still-flagged snapshot rather
     * than aborting the whole migration.
     */
    private function backfillVersions(): void
    {
        DB::table('application_contracts')->orderBy('id')->chunkById(200, function ($contracts) {
            foreach ($contracts as $contract) {
                $status = $this->deriveStatus($contract);

                $snapshot = $this->buildBackfillSnapshot((int) $contract->application_id);

                DB::table('application_contracts')
                    ->where('id', $contract->id)
                    ->update([
                        'version' => $contract->version ?? 1,
                        'status' => $contract->status ?? $status,
                        'data_snapshot' => json_encode($snapshot['data'], JSON_UNESCAPED_UNICODE),
                        'rendered_body' => $contract->rendered_body ?? $snapshot['rendered_body'],
                        'template_hash' => $contract->template_hash ?? $snapshot['template_hash'],
                        // A superseded row's link is dead: clear its token/expiry so it can never
                        // be resolved or signed again.
                        'token' => $status === 'superseded' ? null : $contract->token,
                        'token_expires_at' => $status === 'superseded' ? null : $contract->token_expires_at,
                    ]);
            }
        });
    }

    private function deriveStatus(object $contract): string
    {
        if ($contract->signed_at !== null) {
            return 'signed';
        }

        $applicationStatus = DB::table('applications')
            ->where('id', $contract->application_id)
            ->value('status');

        if ($applicationStatus === 'awaiting_contract_signature') {
            return 'generated';
        }

        return 'superseded';
    }

    /**
     * @return array{data: array<string, mixed>, rendered_body: string, template_hash: string}
     */
    private function buildBackfillSnapshot(int $applicationId): array
    {
        try {
            $application = Application::withoutGlobalScopes()->find($applicationId);

            if ($application !== null) {
                $snapshot = app(BuildContractSnapshotAction::class)->handle($application);

                $data = $snapshot->toArray();
                $data['meta']['backfilled'] = true;

                return [
                    'data' => $data,
                    'rendered_body' => $snapshot->renderedBody,
                    'template_hash' => $snapshot->templateHash,
                ];
            }
        } catch (Throwable $e) {
            report($e);
        }

        return [
            'data' => ['minimum' => [], 'placeholders' => [], 'meta' => ['backfilled' => true]],
            'rendered_body' => '',
            'template_hash' => hash('sha256', ''),
        ];
    }

    /**
     * Tighten the backfilled columns to NOT NULL now that every row has a value, on every
     * engine — the physical test schema (SQLite) must match the production invariant, not merely
     * rely on model behaviour. Laravel's native column change rebuilds the SQLite table (and
     * issues a MODIFY on MySQL/MariaDB), preserving data, the token unique, the generated_by and
     * self-referencing superseded_by_contract_id foreign keys, and the compound indexes.
     */
    private function enforceNotNull(): void
    {
        Schema::table('application_contracts', function (Blueprint $table) {
            $table->unsignedInteger('version')->nullable(false)->change();
            $table->string('status')->nullable(false)->change();
            $table->json('data_snapshot')->nullable(false)->change();
            $table->longText('rendered_body')->nullable(false)->change();
            $table->string('template_hash')->nullable(false)->change();
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $existing): bool => $existing['name'] === $index);
    }
};
