<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repair applications created from leads before CreateApplicationDTO::fromLead()
     * designated the copied father contact as the active guardian.
     */
    public function up(): void
    {
        DB::table('applications')
            ->where('father_is_guardian', false)
            ->where('mother_is_guardian', false)
            ->whereNull('relative_name')
            ->whereNull('relative_phone')
            ->whereNotNull('father_name')
            ->whereNotNull('father_phone')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('leads')
                    ->whereColumn('leads.id', 'applications.lead_id')
                    ->whereColumn('leads.guardian_name', 'applications.father_name')
                    ->whereColumn('leads.whatsapp', 'applications.father_phone');
            })
            ->update(['father_is_guardian' => true]);
    }

    /**
     * The prior value cannot be restored without corrupting applications corrected later.
     */
    public function down(): void
    {
        // Intentionally irreversible.
    }
};
