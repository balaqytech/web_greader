<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = ['name', 'amount', 'type'];

    protected $casts = [
        'amount' => \App\Casts\AmountCast::class,
        'type' => \App\Enums\DiscountType::class,
    ];
}