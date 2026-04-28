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
        Schema::table('applications', function (Blueprint $table) {
            $table->string('contract_token')->nullable()->unique();
            $table->timestamp('contract_token_expires_at')->nullable();
            $table->timestamp('contract_signed_at')->nullable();
            $table->boolean('contract_signed_by_applicant')->nullable();
            $table->string('contract_file_path')->nullable();
            $table->string('contract_signature_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'contract_token',
                'contract_token_expires_at',
                'contract_signed_at',
                'contract_signed_by_applicant',
                'contract_file_path',
                'contract_signature_path',
            ]);
        });
    }
};
