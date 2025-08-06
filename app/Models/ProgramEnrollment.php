<?php

namespace App\Models;

use App\Traits\HasInvoice;
use App\Enums\DiscountType;
use App\ValueObjects\Money;
use App\Contracts\Invoiceable;
use App\States\Enrollment\Signed;
use Spatie\ModelStates\HasStates;
use Illuminate\Database\Eloquent\Model;
use App\States\Enrollment\EnrollmentState;

/**
 * @property EnrollmentState $status
 */
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
        'additional_info',
    ];

    protected $casts = [
        'final_price' => 'decimal:2',
        'contract_signed_at' => 'datetime',
        'status' => EnrollmentState::class,
        'additional_info' => 'array',
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

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            $model->status = \App\States\Enrollment\Draft::class;
            $model->academic_year_id = AcademicYear::where('is_current', true)->first()->id;
        });
    }

    public function getFinalPriceAttribute()
    {
        $finalPrice = $this->program->base_price->value;

        if ($this->discounts->isNotEmpty()) {
            $this->discounts->each(function ($discount) use (&$finalPrice) {
                if ($discount->type === DiscountType::PERCENTAGE) {
                    $finalPrice -= $finalPrice * $discount->amount / 100;
                } elseif ($discount->type === DiscountType::FIXED) {
                    $finalPrice -= $discount->amount;
                }
            });
        }

        return Money::from($finalPrice);
    }
}