<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional outbox for domain lifecycle events. A row is written in the SAME transaction as
 * the state change that produced it (a synchronous listener, no after-commit), so the event
 * record and the domain fact commit or roll back together. Phase 5 only records events; no
 * worker, delivery, or consumer reads this table yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index();
            $table->string('aggregate_type');
            $table->string('aggregate_id');
            $table->json('payload');
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
