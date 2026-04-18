<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AffiliateCategory: string implements HasLabel
{
    case MARKETER = 'marketer';
    case INFLUENCER = 'influencer';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MARKETER => __('admin.affiliate.categories.marketer'),
            self::INFLUENCER => __('admin.affiliate.categories.influencer'),
            self::OTHER => __('admin.affiliate.categories.other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MARKETER => 'info',
            self::INFLUENCER => 'success',
            self::OTHER => 'gray',
        };
    }
}
