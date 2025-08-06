<?php

use App\Enums\DiscountType;
use Illuminate\Support\Collection;
use App\Models\ProgramEnrollment;
use App\ValueObjects\Money;

if (!function_exists('calculate_enrollment_price')) {
    function calculate_enrollment_price(ProgramEnrollment $enrollment, array|Collection $discounts) {
        $price = $enrollment->program->base_price->value();

        foreach ($discounts as $discount) {
            if ($discount->type === DiscountType::PERCENTAGE) {
                $price -= $price * $discount->amount / 100;
            } elseif ($discount->type === DiscountType::FIXED) {
                $price -= $discount->amount;
            }
        }

        return Money::from($price);
    }
}