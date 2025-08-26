<?php

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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_account_id')
                ->constrained('parent_accounts')
                ->onDelete('cascade');
            $table->string('branch_id')
                ->constrained('branches')
                ->onDelete('cascade');
            $table->string('name');
            $table->string('gender');
            $table->date('date_of_birth');
            $table->string('civil_number');
            $table->string('state')->nullable();
            $table->string('province')->nullable();
            $table->string('village')->nullable();
            $table->string('house_number')->nullable();
            $table->string('block_number')->nullable();
            $table->string('category');
            $table->string('parents_relationship')->nullable();
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
