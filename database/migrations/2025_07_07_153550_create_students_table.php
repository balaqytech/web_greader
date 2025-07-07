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
            $table->foreignId('parent_account_id')
                ->constrained('parent_accounts')
                ->onDelete('cascade');
            $table->string('name');
            $table->string('gender');
            $table->date('date_of_birth');
            $table->string('status')->default('active');
            $table->boolean('is_active')->default(true);
            $table->json('additional_info')->nullable(); // For any extra information
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
