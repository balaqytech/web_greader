<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Maps legacy application `status` values to the target baseline states (§9.1).
 * Terminal values (accepted, rejected, cancelled) are unchanged. Historical
 * `application_activities.from_state`/`to_state` values are intentionally left
 * untouched — they are a faithful log of what happened (§9.1).
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const FORWARD_MAP = [
        'draft' => 'awaiting_registration_fee',
        'submitted' => 'awaiting_application_completion',
        'waiting_contract_signature' => 'awaiting_contract_signature',
        'under_review' => 'awaiting_branch_review',
    ];

    public function up(): void
    {
        foreach (self::FORWARD_MAP as $from => $to) {
            DB::table('applications')->where('status', $from)->update(['status' => $to]);
        }
    }

    public function down(): void
    {
        foreach (self::FORWARD_MAP as $from => $to) {
            DB::table('applications')->where('status', $to)->update(['status' => $from]);
        }
    }
};
