<?php

namespace App\Models;

use App\Casts\AmountCast;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Invoice extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'amount',
        'status',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'amount' => AmountCast::class,
        'status' => InvoiceStatus::class,
    ];

    public function invoiceable()
    {
        return $this->morphTo();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            $model->invoice_number = 'INV-' . str_pad(static::max('id') + 1, 6, '0', STR_PAD_LEFT);
            $model->invoice_date = now();
            $model->status = InvoiceStatus::PENDING;
        });
    }

    public function getPaidAmountAttribute()
    {
        return $this->payments()->where('status', PaymentStatus::PAID)->sum('amount');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->amount->sub($this->paid_amount);
    }
}
