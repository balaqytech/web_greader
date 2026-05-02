<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContactType: string implements HasLabel, HasColor
{
    case Father = 'father';
    case Mother = 'mother';
    case Relative = 'relative';
    case Emergency = 'emergency';
    case Other = 'other';

    // Note: 'guardian' is NOT a type. Guardian status is expressed via is_guardian = true.

    public function getLabel(): string
    {
        return match ($this) {
            self::Father => __('admin.application_contacts.type.father'),
            self::Mother => __('admin.application_contacts.type.mother'),
            self::Relative => __('admin.application_contacts.type.relative'),
            self::Emergency => __('admin.application_contacts.type.emergency'),
            self::Other => __('admin.application_contacts.type.other'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Father => 'success',
            self::Mother => 'info',
            self::Relative => 'primary',
            self::Emergency => 'warning',
            self::Other => 'gray',
        };
    }
}
