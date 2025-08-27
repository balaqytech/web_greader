<?php

namespace App\Models;

use App\Traits\HasInvoice;
use App\Enums\DiscountType;
use App\ValueObjects\Money;
use App\Contracts\Invoiceable;
use App\Enums\EnrollmentSource;
use App\States\Enrollment\Draft;
use App\States\Enrollment\Signed;
use Spatie\ModelStates\HasStates;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\States\Enrollment\EnrollmentState;

/**
 * @property EnrollmentState $status
 */
class ProgramEnrollment extends Model implements Invoiceable, Auditable
{
    use HasInvoice, HasStates, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'student_id',
        'program_id',
        'contract_pdf',
        'contract_signed_at',
        'status',
        'academic_year_id',
        'additional_info',
        'already_registered',
        'has_siblings',
        'source',
    ];

    protected $casts = [
        'final_price' => 'decimal:2',
        'contract_signed_at' => 'datetime',
        'additional_info' => 'array',
        'status' => EnrollmentState::class,
        'source' => EnrollmentSource::class,
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
        return $this->belongsToMany(Discount::class, 'discount_program_enrollment')
            ->withTimestamps();
    }

    public function installments()
    {
        return $this->hasMany(Installment::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->status = new Draft($model);
            $model->academic_year_id = AcademicYear::where('is_current', true)->first()->id;
        });
    }

    public function getFinalPriceAttribute()
    {
        return calculate_enrollment_price($this, $this->discounts);
    }
}
