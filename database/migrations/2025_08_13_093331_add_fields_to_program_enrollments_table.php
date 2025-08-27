<?php

use App\Enums\EnrollmentSource;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('program_enrollments', function (Blueprint $table) {
            $table->string('source')->default(EnrollmentSource::WEBSITE->value);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_enrollments', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
