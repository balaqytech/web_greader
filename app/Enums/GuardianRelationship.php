<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GuardianRelationship: string implements HasLabel
{
    case Father = 'father';
    case Mother = 'mother';
    case GrandFather = 'grandfather';
    case GrandMother = 'grandmother';
    case Brother = 'brother';
    case Sister = 'sister';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Relative = 'relative';

    public function getLabel(): string
    {
        return match ($this) {
            self::Father => __('admin.guardian.relationships.father'),
            self::Mother => __('admin.guardian.relationships.mother'),
            self::GrandFather => __('admin.guardian.relationships.grandfather'),
            self::GrandMother => __('admin.guardian.relationships.grandmother'),
            self::Brother => __('admin.guardian.relationships.brother'),
            self::Sister => __('admin.guardian.relationships.sister'),
            self::Uncle => __('admin.guardian.relationships.uncle'),
            self::Aunt => __('admin.guardian.relationships.aunt'),
            self::Relative => __('admin.guardian.relationships.relative'),
        };
    }
}
