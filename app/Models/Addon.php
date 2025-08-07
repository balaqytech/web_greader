<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Addon extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'description',
        'price',
        'type',
        'is_active',
    ];

    protected $casts = [
        'type' => \App\Enums\AddonType::class,
        'price' => \App\Casts\AmountCast::class,
        'is_active' => 'boolean',
    ];
}