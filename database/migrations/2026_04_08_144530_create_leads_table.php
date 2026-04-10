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
            $table->string('phone');
            $table->json('data')->nullable();

            $table->string('status')->default('new');
            $table->string('source')->default('website');

            $table->foreignId('converted_student_id')->nullable()->constrained(table: 'students', column: 'id')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('source');
            $table->index('phone');
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
