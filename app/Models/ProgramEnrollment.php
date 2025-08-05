<?php

namespace App\Models;

use App\Traits\HasInvoice;
use App\Contracts\Invoiceable;
use App\Enums\EnrollmentStatus;
use App\States\Enrollment\EnrollmentState;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;

class ProgramEnrollment extends Model implements Invoiceable
{
    use HasInvoice, HasStates;

    protected $fillable = [
        'student_id',
        'program_id',
        'contract_pdf',
        'contract_signed_at',
        'status',
        'academic_year_id',
    ];

    protected $casts = [
        'final_price' => 'decimal:2',
        'contract_signed_at' => 'datetime',
        'status' => EnrollmentState::class,
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'discount_program_enrollment');
    }

    public function installments()
    {
        return $this->hasMany(Installment::class);
    }

    public function isSigned(): bool
    {
        return $this->status === EnrollmentStatus::SIGNED;
    }

    public function getFinalPriceAttribute()
    {
        return $this->invoice->amount ?? 0;
    }
}