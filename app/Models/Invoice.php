<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'enrollment_id',
        'coupon_id',
        'total_amount',
        'due_date',
        'status',
    ];

    protected $casts = [
        'status' => \App\Enums\InvoiceStatus::class,
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function installments()
    {
        return $this->hasMany(Installment::class);
    }
}
