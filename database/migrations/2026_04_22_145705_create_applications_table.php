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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no')->unique();
            $table->foreignId('lead_id')->unique()->nullable()->constrained()->nullOnDelete();
            $table->foreignId('season_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->foreignId('affiliate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('website');

            // Student data
            $table->string('student_name');
            $table->string('student_gender')->nullable()->index();
            $table->date('student_birth_date')->nullable();
            $table->string('student_civil_number')->nullable();
            $table->string('student_state')->nullable();
            $table->string('student_governorate')->nullable();
            $table->string('student_village')->nullable();
            $table->string('student_house_number')->nullable();
            $table->string('student_parents_social_status')->nullable();
            $table->string('relationship_with_guardian')->nullable();

            // Father data
            $table->string('father_name')->nullable();
            $table->string('father_phone')->nullable();
            $table->string('father_email')->nullable();
            $table->string('father_id_number')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('father_work_address')->nullable();
            $table->string('father_work_phone')->nullable();
            $table->boolean('father_is_guardian')->default(false);

            // Mother data
            $table->string('mother_name')->nullable();
            $table->string('mother_phone')->nullable();
            $table->string('mother_email')->nullable();
            $table->string('mother_id_number')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('mother_work_address')->nullable();
            $table->string('mother_work_phone')->nullable();
            $table->boolean('mother_is_guardian')->default(false);

            // Relative data
            $table->string('relative_name')->nullable();
            $table->string('relative_phone')->nullable();
            $table->string('relative_email')->nullable();
            $table->string('relative_id_number')->nullable();
            $table->string('relative_occupation')->nullable();
            $table->string('relative_work_address')->nullable();
            $table->string('relative_work_phone')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
