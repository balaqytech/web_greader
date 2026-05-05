<?php

namespace App\Filament\Resources\Students\Pages;

use App\Actions\Students\UpdateStudentAction;
use App\DTOs\Students\StudentDataDTO;
use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            $dto = StudentDataDTO::fromValidated($data);

            return app(UpdateStudentAction::class)->execute($record, $dto);
        } catch (ValidationException $e) {
            throw $e;
        }
    }
}
