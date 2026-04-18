<?php

namespace App\States\Affiliates;

class Rejected extends AffiliateState
{
    public static string $name = 'rejected';

    public static function getLabel(): string
    {
        return __('admin.affiliate.states.rejected');
    }

    public static function color(): string
    {
        return 'danger';
    }
}
