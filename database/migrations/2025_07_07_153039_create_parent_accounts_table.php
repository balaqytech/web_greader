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
        Schema::create('parent_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('civil_number')->nullable();
            $table->string('occupation')->nullable();
            $table->string('occupation_address')->nullable();
            $table->string('occupation_phone')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->json('additional_info')->nullable();
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_accounts');
    }
};
