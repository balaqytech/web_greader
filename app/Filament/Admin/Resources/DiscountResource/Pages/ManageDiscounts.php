<?php

namespace App\Filament\Admin\Resources\DiscountResource\Pages;

use App\Filament\Admin\Resources\DiscountResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDiscounts extends ManageRecords
{
    protected static string $resource = DiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
