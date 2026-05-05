<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class InvoicesPage extends Page
{
    protected string $view = 'filament.pages.invoices-page';

    public static function getNavigationLabel(): string
    {
        return 'الفواتير والسندات';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.financial_and_fees');
    }
}