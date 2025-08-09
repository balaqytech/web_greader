<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Znck\Eloquent\Traits\BelongsToThrough;

class Installment extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use BelongsToThrough;

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

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function student()
    {
        return $this->belongsToThrough(Student::class, ProgramEnrollment::class);
    }
}