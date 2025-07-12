<?php

namespace App\Enums;

enum CouponType: string
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';
}
