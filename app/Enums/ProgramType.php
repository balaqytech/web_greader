<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProgramType: string implements HasLabel
{
    case Academic = 'academic';
    case Summer = 'summer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Academic => __('admin.program_type.academic'),
            self::Summer => __('admin.program_type.summer'),
        };
    }
}
