<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PaymentPage extends Page
{
    protected string $view = 'filament.pages.payment-page';

    public static function getNavigationLabel(): string
    {
        return 'العقود والمدفوعات';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.financial_and_fees');
    }
}