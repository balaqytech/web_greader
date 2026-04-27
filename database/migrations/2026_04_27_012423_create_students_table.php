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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('season_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('civil_number')->nullable();
            $table->string('state')->nullable();
            $table->string('governorate')->nullable();
            $table->string('village')->nullable();
            $table->string('house_number')->nullable();
            $table->string('parents_social_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
