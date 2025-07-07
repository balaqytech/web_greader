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
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., "2025-2026"
            $table->date('start_date'); // Start date of the academic year
            $table->date('end_date'); // End date of the academic year
            $table->boolean('is_active')->default(true); // Indicates if the academic year is currently active
            $table->json('additional_info')->nullable(); // For any extra information related to the academic year, such as holidays, special events, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
