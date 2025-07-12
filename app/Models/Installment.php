<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    protected $fillable = [
        'invoice_id',
        'amount',
        'due_date',
        'paid_date',
        'status',
    ];

    protected $casts = [
        'status' => \App\Enums\InstallmentStatus::class,
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
