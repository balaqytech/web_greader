<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphOne;

interface Invoiceable
{
    public function invoice(): MorphOne;
}
