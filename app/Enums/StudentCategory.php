<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StudentCategory: string implements HasLabel
{
    case New = 'new';
    case Siblings = 'siblings';
    case Old = 'old';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => __('frontend.student_category.new'),
            self::Siblings => __('frontend.student_category.siblings'),
            self::Old => __('frontend.student_category.old'),
        };
    }
}