<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Gender: string implements HasLabel
{
    case Male = 'male';
    case Female = 'female';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Male => __('app.gender.male'),
            self::Female => __('app.gender.female'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Male => 'info',
            self::Female => 'danger',
        };
    }
}
