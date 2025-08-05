<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactMethod: string implements HasLabel
{
    case EMAIL = 'email';
    case PHONE = 'phone';
    case WHATSAPP = 'whatsapp';

    public function getLabel(): string
    {
        return match ($this) {
            self::EMAIL => __('admin.contact_method.email'),
            self::PHONE => __('admin.contact_method.phone'),
            self::WHATSAPP => __('admin.contact_method.whatsapp'),
        };
    }
}
