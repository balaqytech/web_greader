<?php

namespace App\Enums;

enum AddonType: string implements \Filament\Support\Contracts\HasLabel
{
    case ONE_TIME = 'one_time';
    case MONTHLY = 'monthly';

    public function getLabel(): string
    {
        return match ($this) {
            self::ONE_TIME => __('admin.addon.types.one_time'),
            self::MONTHLY => __('admin.addon.types.monthly'),
        };
    }
}
