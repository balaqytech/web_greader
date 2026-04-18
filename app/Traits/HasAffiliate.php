<?php

namespace App\Traits;

use App\Models\Affiliate;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasAffiliate
{
    /**
     * This runs automatically when the model is instantiated.
     */
    public function initializeHasAffiliate(): void
    {
        $this->mergeFillable(['affiliate_id']);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
