<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correction requests raised during branch review (§5.6, §6.2).
 *
 * At most one open correction (`completed_at IS NULL`) per application is enforced by locking
 * the application row in the request/complete actions — matching the payment-domain precedent —
 * not by a partial index, which is not portable across this project's SQLite test engine and
 * MySQL/MariaDB.
 *
 * `data_before` freezes the confirmed-minimum + placeholder snapshot at request time so the
 * completion classifier has a stable baseline. `is_contract_relevant` is null until completion
 * records the computed outcome. `restrictOnDelete` on the actor FKs keeps the audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_corrections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('application_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('reason');

            // [{item: string, done: bool}]
            $table->json('checklist');

            // Freeze of the confirmed-minimum + placeholder fields at request time.
            $table->json('data_before');

            // Null until completion records the computed classification outcome.
            $table->boolean('is_contract_relevant')->nullable();

            $table->timestamp('requested_at');

            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['application_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_corrections');
    }
};
