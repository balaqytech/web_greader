<?php

use App\Models\Branch;
use App\Models\Season;
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
        Schema::create('reading_assessment_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('student_name');
            $table->integer('age');
            $table->string('grade_level');
            $table->string('guardian_name');
            $table->string('whatsapp');
            $table->foreignIdFor(Branch::class)->constrained()->cascadeOnDelete();
            $table->string('status')->default('new');
            $table->string('source')->default('website');
            $table->json('additional_info')->nullable();
            $table->timestamps();

            $table->index('branch_id');
            $table->index('whatsapp');
            $table->unique(['whatsapp', 'student_name', 'branch_id'], 'rasf_whatsapp_student_branch_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_assessment_form_submissions');
    }
};
