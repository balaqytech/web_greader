<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Discount extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['name', 'amount', 'type'];

    protected $casts = [
        'type' => \App\Enums\DiscountType::class,
    ];
}