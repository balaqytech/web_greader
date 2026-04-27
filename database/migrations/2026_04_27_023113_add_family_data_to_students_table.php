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
        Schema::table('students', function (Blueprint $table) {
            $table->json('father_data')->nullable()->after('parents_social_status');
            $table->json('mother_data')->nullable()->after('father_data');
            $table->json('relative_data')->nullable()->after('mother_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['father_data', 'mother_data', 'relative_data']);
        });
    }
};
