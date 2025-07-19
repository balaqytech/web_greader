<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    protected $fillable = [
        'program_enrollment_id',
        'amount',
        'due_date',
        'paid_date',
        'status',
    ];

    protected $casts = [
        'amount' => \App\Casts\AmountCast::class,
        'due_date' => 'datetime',
        'paid_date' => 'datetime',
        'status' => \App\Enums\InstallmentStatus::class,
    ];

    public function programEnrollment()
    {
        return $this->belongsTo(ProgramEnrollment::class);
    }
}