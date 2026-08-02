<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditable models use both integer IDs and string IDs. In particular, Setting uses its
 * string `key` as the primary key, so the shared polymorphic identifier must accommodate
 * either type. Existing numeric identifiers remain valid string representations.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $tableName = config('audit.drivers.database.table', 'audits');

        Schema::connection($connection)->table($tableName, function (Blueprint $table): void {
            $table->string('auditable_id')->change();
        });
    }

    /**
     * String audit identifiers cannot be converted back to integers without data loss.
     */
    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
