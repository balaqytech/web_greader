<?php

namespace App\Traits;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasInvoice
{
    public function invoice(): MorphOne
    {
        return $this->morphOne(Invoice::class, 'invoiceable', 'invoiceable_type', 'invoiceable_id');
    }
}
