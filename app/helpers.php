<?php

use App\Enums\DiscountType;
use Illuminate\Support\Collection;
use App\Models\ProgramEnrollment;
use App\ValueObjects\Money;

if (!function_exists('calculate_enrollment_price')) {
    function calculate_enrollment_price(ProgramEnrollment $enrollment, array|Collection $discounts)
    {
        $price = $enrollment->program->base_price->value();

        foreach ($discounts as $discount) {
            if ($discount->type === DiscountType::PERCENTAGE) {
                $price -= $enrollment->program->base_price->value() * $discount->amount / 100;
            } elseif ($discount->type === DiscountType::FIXED) {
                $price -= $discount->amount;
            }
        }

        return Money::from($price);
    }
}


if (!function_exists('convert_eastern_arabic_to_arabic')) {
    function convert_eastern_arabic_to_arabic(string $text): string
    {
        $easternArabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $arabicDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        return str_replace($easternArabicDigits, $arabicDigits, $text);
    }
}
