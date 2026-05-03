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
        Schema::create('application_students', function (Blueprint $table) {
            $table->id();

            $table->foreignId('application_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('civil_number')->nullable()->index(); // Required for submission — validated by ValidateApplicationCompletionAction.

            $table->string('state')->nullable();
            $table->string('governorate')->nullable();
            $table->string('village')->nullable();
            $table->string('house_number')->nullable();

            $table->string('relationship_with_guardian')->nullable();
            $table->string('parents_social_status')->nullable();

            $table->timestamps();

            $table->unique('application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_students');
    }
};
