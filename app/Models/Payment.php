<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'transaction_id',
        'method',
        'amount',
        'payment_date',
        'attachment',
        'status',
    ];

    protected $casts = [
        'amount' => \App\Casts\AmountCast::class,
        'payment_date' => 'datetime',
        'method' => \App\Enums\PaymentMethod::class,
        'status' => \App\Enums\PaymentStatus::class,
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
