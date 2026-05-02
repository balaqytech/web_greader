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
        Schema::create('application_contracts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('application_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('token')->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();

            $table->timestamp('signed_at')->nullable();
            $table->boolean('signed_by_applicant')->nullable();

            $table->string('file_path')->nullable();
            $table->string('signature_path')->nullable();

            $table->timestamps();

            $table->unique('application_id', 'application_contracts_application_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_contracts');
    }
};
