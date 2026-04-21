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
        Schema::create('bot_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->default('whatsapp');
            $table->string('sender_name')->nullable();
            $table->string('whatsapp')->unique();
            $table->string('status')->default('new');
            $table->text('notes')->nullable();
            $table->json('additional_data')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_contacts');
    }
};
