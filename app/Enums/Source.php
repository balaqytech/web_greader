<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Source: string implements HasColor, HasLabel
{
    case WEBSITE = 'website';
    case WHATSAPP_BOT = 'whatsapp_bot';
    case DASHBOARD = 'dashboard';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::WEBSITE => __('admin.sources.website'),
            self::WHATSAPP_BOT => __('admin.sources.whatsapp_bot'),
            self::DASHBOARD => __('admin.sources.dashboard'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::WEBSITE => 'info',
            self::WHATSAPP_BOT => 'success',
            self::DASHBOARD => 'gray',
        };
    }
}
