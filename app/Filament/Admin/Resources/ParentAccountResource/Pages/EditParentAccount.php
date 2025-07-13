<?php

namespace App\Filament\Admin\Resources\ParentAccountResource\Pages;

use App\Filament\Admin\Resources\ParentAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParentAccount extends EditRecord
{
    protected static string $resource = ParentAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [];
    }
}
