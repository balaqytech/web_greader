<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API attribution metadata for audits. `api_token_id` records which Sanctum personal-access
 * token drove a change; it is intentionally a plain nullable column with NO foreign key, so
 * revoking (deleting) a token never erases or nulls the historical attribution. `api_ability`
 * records which service ability authorized it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->unsignedBigInteger('api_token_id')->nullable()->after('user_type');
            $table->string('api_ability')->nullable()->after('api_token_id');

            $table->index('api_token_id');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropIndex(['api_token_id']);
            $table->dropColumn(['api_token_id', 'api_ability']);
        });
    }
};
