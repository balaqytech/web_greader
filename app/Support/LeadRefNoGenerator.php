<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\Scopes\BranchScope;
use Illuminate\Support\Facades\DB;

final class LeadRefNoGenerator
{
    /**
     * Generate the next unique lead reference for today (Ymd + 6-digit sequence).
     */
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $prefix = now()->format('Ymd');

            $latest = Lead::query()
                ->withoutGlobalScope(BranchScope::class)
                ->where('ref_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('ref_no')
                ->value('ref_no');

            $sequence = 1;

            if (is_string($latest) && str_starts_with($latest, $prefix)) {
                $sequence = (int) substr($latest, strlen($prefix)) + 1;
            }

            return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        });
    }
}
