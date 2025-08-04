<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'amount',
        'status',
        'payment_method',
        'payment_status',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'amount' => 'decimal:2',
        'status' => InvoiceStatus::class,
        'payment_method' => PaymentMethod::class,
        'payment_status' => PaymentStatus::class,
    ];

    public function invoiceable()
    {
        return $this->morphTo();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
