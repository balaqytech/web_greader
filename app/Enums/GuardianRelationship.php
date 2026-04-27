<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GuardianRelationship: string implements HasLabel
{
    case Father = 'father';
    case Mother = 'mother';
    case Relative = 'relative';

    public function getLabel(): string
    {
        return match ($this) {
            self::Father => __('admin.guardian_relationship.father'),
            self::Mother => __('admin.guardian_relationship.mother'),
            self::Relative => __('admin.guardian_relationship.relative'),
        };
    }
}
