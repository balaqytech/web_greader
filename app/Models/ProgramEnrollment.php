<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramEnrollment extends Model
{
    protected $fillable = [
        'student_id',
        'program_id',
        'final_price',
        'contract_pdf',
        'contract_signed_at',
        'status',
        'academic_year_id',
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
}