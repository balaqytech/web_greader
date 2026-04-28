<?php

namespace App\Filament\Resources\Students\Pages;

use App\Actions\Students\CreateStudentAction;
use App\DTOs\Students\StudentDataDTO;
use App\Filament\Resources\Students\StudentResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $dto = StudentDataDTO::fromValidated($data);

            return app(CreateStudentAction::class)->execute($dto);
        } catch (ValidationException $e) {
            Notification::make()
                ->title(__('admin.student.failed_to_create_student'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }
}
