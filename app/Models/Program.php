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
        'is_active',
        'additional_info',
    ];

    protected $casts = [
        'type' => \App\Enums\ProgramType::class,
        'payment_type' => \App\Enums\ProgramPaymentType::class,
        'additional_info' => 'json',
        'is_active' => 'boolean',
    ];
}
