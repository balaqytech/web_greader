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
        Schema::create('application_contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('application_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('relationship')->nullable();
            // father, mother, uncle, aunt, grandfather, etc.

            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('id_number')->nullable()->index();

            $table->string('occupation')->nullable();
            $table->string('work_address')->nullable();
            $table->string('work_phone')->nullable();

            $table->boolean('is_guardian')->default(false);

            $table->timestamps();

            $table->index(['application_id', 'type']);
            $table->index(['application_id', 'is_guardian']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_contacts');
    }
};
