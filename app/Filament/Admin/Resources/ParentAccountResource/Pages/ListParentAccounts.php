<?php

namespace App\Filament\Admin\Resources\ParentAccountResource\Pages;

use App\Filament\Admin\Resources\ParentAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParentAccounts extends ListRecords
{
    protected static string $resource = ParentAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
