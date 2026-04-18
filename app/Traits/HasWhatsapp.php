<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasWhatsapp
{
    protected function whatsapp(): Attribute
    {
        return Attribute::make(
            set: fn(string $value) => normalize_phone_number(
                convert_eastern_arabic_to_arabic($value)
            ),
        );
    }
}
