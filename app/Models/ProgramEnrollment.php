<?php

namespace App\Models;

use App\Traits\HasInvoice;
use App\Contracts\Invoiceable;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;

class ProgramEnrollment extends Model implements Invoiceable
{
    use HasInvoice;

    protected $fillable = [
        'student_id',
        'program_id',
        'final_price',
        'contract_pdf',
        'contract_signed_at',
        'status',
        'academic_year_id',
    ];

    protected $casts = [
        'final_price' => 'decimal:2',
        'contract_signed_at' => 'datetime',
        'status' => EnrollmentStatus::class,
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

    public function isSigned(): bool
    {
        return $this->status === EnrollmentStatus::SIGNED;
    }
}