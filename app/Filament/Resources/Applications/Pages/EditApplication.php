<?php

namespace App\Filament\Resources\Applications\Pages;

use App\DTOs\Application\UpdateApplicationDataDTO;
use App\Filament\Resources\Applications\ApplicationResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        UpdateApplicationDataDTO::fromValidated($data);

        $record->update($data);

        return $record->fresh();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
