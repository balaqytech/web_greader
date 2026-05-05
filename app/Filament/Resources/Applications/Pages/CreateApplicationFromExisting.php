<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\Concerns\CreatesApplicationRecord;
use App\Filament\Resources\Applications\Schemas\CreateFromExistingForm;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

class CreateApplicationFromExisting extends CreateRecord
{
    use CreatesApplicationRecord;

    protected static string $resource = ApplicationResource::class;

    public function getTitle(): string
    {
        return __('admin.application.actions.create_from_existing_title');
    }

    public function form(Schema $schema): Schema
    {
        return CreateFromExistingForm::configure($schema);
    }

    /**
     * Exclude guardian_id and student_id from the saved data — they are lookup fields only.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['guardian_id'], $data['student_id']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
