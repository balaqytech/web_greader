<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_idempotency_key')->nullable()->unique();
            $table->string('receipt_request_hash')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['receipt_idempotency_key']);
            $table->dropColumn(['receipt_idempotency_key', 'receipt_request_hash']);
        });
    }
};
