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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('season_id')->constrained()->restrictOnDelete();
            $table->string('program_type')->index();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->string('whatsapp');
            $table->string('guardian_name');
            $table->string('student_name');
            $table->json('data')->nullable();
            $table->string('status')->index();
            $table->string('source')->nullable()->index();
            $table->timestamps();

            $table->unique(['whatsapp', 'program_id', 'season_id', 'branch_id', 'student_name'], 'leads_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
