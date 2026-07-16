<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal key/value settings store. Values are stored as text and interpreted by typed
 * accessors (see `App\Support\Settings\PaymentSettings`) rather than by this table, so a
 * setting's type can change without a schema migration.
 *
 * `value` is nullable and that is meaningful: NULL means "never configured", which is
 * distinct from an empty string. Payment creation stays blocked while the registration fee
 * is NULL — there is deliberately no default, because a guessed or zero fee would silently
 * let applications through the fee gate for free.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
