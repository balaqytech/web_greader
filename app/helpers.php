<?php

if (! function_exists('convert_eastern_arabic_to_arabic')) {
    function convert_eastern_arabic_to_arabic(string $text): string
    {
        $easternArabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $arabicDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($easternArabicDigits, $arabicDigits, $text);
    }
}

if (! function_exists('normalize_phone_number')) {
    function normalize_phone_number(string $phone): string
    {
        // remove spaces, dashes, parentheses
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // convert 00 prefix to +
        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        }

        // if starts with 0 → assume local and prepend Oman country code (+968)
        if (str_starts_with($phone, '0')) {
            $phone = '+968'.substr($phone, 1);
        }

        // if no + at all → force it (dangerous but better than garbage)
        if (! str_starts_with($phone, '+') && ! str_starts_with($phone, '968')) {
            $phone = '+968'.$phone;
        }

        // final validation (basic E.164: + followed by 8–15 digits)
        if (! preg_match('/^\+[1-9]\d{7,14}$/', $phone)) {
            throw new InvalidArgumentException('Invalid phone number format.');
        }

        return $phone;
    }
}
