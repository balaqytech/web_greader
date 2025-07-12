<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type', // 'fixed' or 'percentage'
        'value',
        'valid_from',
        'valid_to',
        'usage_limit',
        'usage_count',
        'applicable_program_id',
    ];

    protected $casts = [
        'type' => \App\Enums\CouponType::class,
    ];

    public function program()
    {
        return $this->belongsTo(Program::class, 'applicable_program_id');
    }
}
