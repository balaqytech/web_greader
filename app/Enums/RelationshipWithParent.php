<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RelationshipWithParent: string implements HasLabel
{
    case Father = 'father';
    case Mother = 'mother';
    case Brother = 'brother';
    case Sister = 'sister';
    case Grandfather = 'grandfather';
    case Grandmother = 'grandmother';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Cousin = 'cousin';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Father => __('admin.student.relationships.father'),
            self::Mother => __('admin.student.relationships.mother'),
            self::Brother => __('admin.student.relationships.brother'),
            self::Sister => __('admin.student.relationships.sister'),
            self::Grandfather => __('admin.student.relationships.grandfather'),
            self::Grandmother => __('admin.student.relationships.grandmother'),
            self::Uncle => __('admin.student.relationships.uncle'),
            self::Aunt => __('admin.student.relationships.aunt'),
            self::Cousin => __('admin.student.relationships.cousin'),
            self::Other => __('admin.student.relationships.other'),
        };
    }
}
