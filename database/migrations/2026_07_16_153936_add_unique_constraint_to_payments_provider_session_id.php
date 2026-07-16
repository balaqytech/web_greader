<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A Thawani session must belong to exactly one payment attempt — that binding is what
 * `ResolvePaymentFromProviderAction` relies on to refuse a session id claimed by more than one
 * row. Additive: replaces the existing plain index with a unique one, so lookups stay indexed.
 *
 * Guarded rather than unconditional: this project's databases (including any environment that
 * has drifted ahead of migrations, or was seeded before this constraint existed) may already
 * hold duplicate non-null `provider_session_id` values from before this fix landed. Adding a
 * unique index straight onto such data would fail the migration with an opaque database error;
 * this checks first and fails loudly with the offending values instead, so whoever runs it
 * knows exactly what to reconcile before retrying.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoDuplicateSessionIds();

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['provider_session_id']);
            $table->unique('provider_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider_session_id']);
            $table->index('provider_session_id');
        });
    }

    private function assertNoDuplicateSessionIds(): void
    {
        $duplicates = DB::table('payments')
            ->select('provider_session_id')
            ->whereNotNull('provider_session_id')
            ->groupBy('provider_session_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('provider_session_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Cannot add a unique constraint on payments.provider_session_id: %d duplicate session id(s) exist (%s). Resolve these rows manually — each session must belong to exactly one payment — before migrating.',
                $duplicates->count(),
                $duplicates->implode(', '),
            ));
        }
    }
};
