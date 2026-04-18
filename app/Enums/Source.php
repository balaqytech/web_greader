<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Source: string implements HasLabel
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

    public function color(): string
    {
        return match ($this) {
            self::WEBSITE => 'gray',
            self::WHATSAPP_BOT => 'success',
            self::DASHBOARD => 'info',
        };
    }
}
