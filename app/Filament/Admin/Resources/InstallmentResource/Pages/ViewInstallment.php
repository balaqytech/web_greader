<?php

namespace App\Filament\Admin\Resources\InstallmentResource\Pages;

use App\Filament\Admin\Resources\InstallmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInstallment extends ViewRecord
{
    protected static string $resource = InstallmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
