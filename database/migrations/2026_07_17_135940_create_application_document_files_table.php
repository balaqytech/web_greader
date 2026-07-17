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
        Schema::create('application_document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_document_id')->constrained('application_documents')->cascadeOnDelete();
            $table->string('file_path')->unique();
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->nullableMorphs('uploaded_by', indexName: 'application_document_files_uploaded_by_index');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_document_files');
    }
};
