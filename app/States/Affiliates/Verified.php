<?php

namespace App\States\Affiliates;

class Verified extends AffiliateState
{
    public static string $name = 'verified';

    public static function getLabel(): string
    {
        return __('admin.affiliate.states.verified');
    }

    public static function color(): string
    {
        return 'success';
    }
}
