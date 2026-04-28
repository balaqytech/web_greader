<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Actions\Applications\UpdateApplicationDataAction;
use App\DTOs\Application\UpdateApplicationDataDTO;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Application $record */
        $dto = UpdateApplicationDataDTO::fromValidated($data);

        return app(UpdateApplicationDataAction::class)->execute($record, $dto);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
