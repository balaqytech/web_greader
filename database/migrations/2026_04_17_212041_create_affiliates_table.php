<?php

use App\Models\User;
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
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('category')->default('marketer');
            $table->string('whatsapp')->unique();
            $table->string('password');
            $table->string('email')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignIdFor(User::class, 'rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('creation_source')->default('website');
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('affiliate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('affiliate_code_snapshot')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliates');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['affiliate_id']);
            $table->dropColumn('affiliate_id');
            $table->dropColumn('affiliate_code_snapshot');
        });
    }
};
