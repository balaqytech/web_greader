<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * At-most-once processing for mutating service-API requests.
 *
 * A row is first written as a reservation (response_status null, short processing lease in
 * expires_at); on completion it stores the response status/body and extends expires_at to the
 * replay TTL. `token_id` has NO cascade delete constraint (nullOnDelete) so a revoked token
 * cannot wipe in-flight reservations. Uniqueness is per (token_id, key). Expired rows —
 * abandoned leases and lapsed completed replays alike — are pruned via expires_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('token_id')->nullable()
                ->constrained('personal_access_tokens')->nullOnDelete();
            $table->string('key', 128);
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(['token_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_keys');
    }
};
