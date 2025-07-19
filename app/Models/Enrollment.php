<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = [
        'contract_pdf',
        'contract_signed_at',
        'status',
        'student_id',
        'program_id',
        'academic_year_id',
    ];

    protected $casts = [
        'contract_signed_at' => 'datetime',
        'status' => \App\Enums\EnrollmentStatus::class,
    ];

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function addons()
    {
        return $this->belongsToMany(Addon::class, 'enrollment_addon');
    }

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
}
