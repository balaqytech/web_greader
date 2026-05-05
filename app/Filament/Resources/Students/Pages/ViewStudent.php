<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Guardians\GuardianResource;
use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('show_guardian')
                ->label(__('admin.student.actions.show_guardian'))
                ->icon('heroicon-o-user')
                ->color('primary')
                ->url(fn($record) => GuardianResource::getUrl('view', ['record' => $record->guardian])),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
