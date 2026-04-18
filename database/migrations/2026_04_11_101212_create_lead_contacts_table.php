<?php

use App\Models\Affiliate;
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
        Schema::create('lead_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('contacted_by');
            $table->string('contact_method')->index();
            $table->string('contact_result')->index();
            $table->text('notes')->nullable();
            $table->dateTime('follow_up_at')->nullable()->index();
            $table->dateTime('contacted_at')->index();
            $table->foreignIdFor(Affiliate::class, 'affiliate_id')->nullable();
            $table->string('affiliate_code_snapshot')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_contacts');
    }
};
