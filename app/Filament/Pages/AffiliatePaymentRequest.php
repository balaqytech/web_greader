<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AffiliatePaymentRequest extends Page
{
    protected string $view = 'filament.pages.affiliate-payment-request';

    public static function getNavigationLabel(): string
    {
        return 'مدفوعات العمولات';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.financial_and_fees');
    }
}