<?php

use App\Enums\ProgramType;
use App\Models\Season;
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
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->timestamps();
        });

        Season::create([
            'name' => 'صيف ' . date('Y'),
            'type' => ProgramType::Summer,
            'start_date' => date('Y') . '-06-01',
            'end_date' => date('Y') . '-08-31',
            'is_active' => true,
        ]);

        Season::create([
            'name' => 'العام الدراسي ' . date('Y')  . '-' . date('Y') + 1,
            'type' => ProgramType::Academic,
            'start_date' => date('Y') . '-09-01',
            'end_date' => (date('Y') + 1) . '-03-31',
            'is_active' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
