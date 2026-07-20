<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use InvalidArgumentException;

final class ValidPhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.string')->translate(['attribute' => $attribute]);

            return;
        }

        try {
            normalize_phone_number(convert_eastern_arabic_to_arabic($value));
        } catch (InvalidArgumentException) {
            $fail('validation.regex')->translate(['attribute' => $attribute]);
        }
    }
}
