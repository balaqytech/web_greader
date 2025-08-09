<?php

namespace App\Filament\Admin\Resources\InstallmentResource\Pages;

use App\Filament\Admin\Resources\InstallmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInstallments extends ListRecords
{
    protected static string $resource = InstallmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
