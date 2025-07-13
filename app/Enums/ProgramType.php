<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProgramType: string implements HasLabel
{
    case ANNUAL = 'annual';
    case SUMMER = 'summer';

    public function getLabel(): string
    {
        return match ($this) {
            self::ANNUAL => __('admin.program.types.annual'),
            self::SUMMER => __('admin.program.types.summer'),
        };
    }
}
