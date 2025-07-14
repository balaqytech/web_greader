<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CouponType: string implements HasLabel
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';

    public function getLabel(): string
    {
        return match ($this) {
            self::FIXED => __('admin.coupon.types.fixed'),
            self::PERCENTAGE => __('admin.coupon.types.percentage'),
        };
    }
}
