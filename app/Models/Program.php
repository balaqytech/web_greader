<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'base_price',
        'payment_type',
        'contract',
        'is_active',
        'additional_info',
    ];

    protected $casts = [
        'base_price' => \App\Casts\AmountCast::class,
        'type' => \App\Enums\ProgramType::class,
        'payment_type' => \App\Enums\ProgramPaymentType::class,
        'additional_info' => 'json',
        'is_active' => 'boolean',
    ];
}