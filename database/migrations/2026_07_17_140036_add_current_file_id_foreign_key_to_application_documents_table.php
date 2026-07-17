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
        Schema::table('application_documents', function (Blueprint $table) {
            $table->foreign('current_file_id')
                ->references('id')
                ->on('application_document_files')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropForeign(['current_file_id']);
        });
    }
};
