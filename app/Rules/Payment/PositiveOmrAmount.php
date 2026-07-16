<?php

declare(strict_types=1);

namespace App\Rules\Payment;

use App\Support\Money\OmrAmount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts only a canonical, strictly-positive OMR amount: a plain decimal string with
 * exactly three decimal places (OMR's real precision — 1 OMR = 1000 baisa).
 *
 * The precision is required rather than coerced. Accepting "25.5" and silently reading it
 * as 25.500 would hide a genuine mistake about what is being charged, and every amount that
 * reaches the provider must convert to an exact integer baisa value with no rounding
 * decision in between.
 *
 * Zero is rejected: a zero fee is indistinguishable in effect from no fee at all, and would
 * let applications through the fee gate for free while appearing configured.
 */
class PositiveOmrAmount implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('validation.omr_amount.format')->translate();

            return;
        }

        $amount = (string) $value;

        if (! OmrAmount::isValid($amount)) {
            $fail('validation.omr_amount.format')->translate();

            return;
        }

        if (! OmrAmount::fromString($amount)->isPositive()) {
            $fail('validation.omr_amount.positive')->translate();
        }
    }
}
