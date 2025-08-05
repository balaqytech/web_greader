<?php

namespace App\Models;

use App\Traits\HasInvoice;
use App\Enums\DiscountType;
use App\ValueObjects\Money;
use App\Contracts\Invoiceable;
use App\Enums\EnrollmentStatus;
use Spatie\ModelStates\HasStates;
use Illuminate\Database\Eloquent\Model;
use App\States\Enrollment\EnrollmentState;

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