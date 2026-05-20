<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bot_contacts', function (Blueprint $table) {
            $table->text('conversation_summary')->nullable()->after('status');
            $table->text('rejection_reason')->nullable()->after('conversation_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_contacts', function (Blueprint $table) {
            $table->dropColumn(['conversation_summary', 'rejection_reason']);
        });
    }
};
