<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EnrollmentSource: string implements HasLabel
{
    case WEBSITE = 'website';
    case WHATSAPP = 'whatsapp';

    public function getLabel(): string
    {
        return match ($this) {
            self::WEBSITE => __('admin.enrollment_source.website'),
            self::WHATSAPP => __('admin.enrollment_source.whatsapp'),
        };
    }
}