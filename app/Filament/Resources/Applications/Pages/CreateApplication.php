<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\Concerns\CreatesApplicationRecord;
use App\Filament\Resources\Applications\Schemas\CreateApplicationForm;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

class CreateApplication extends CreateRecord
{
    use CreatesApplicationRecord;

    protected static string $resource = ApplicationResource::class;

    public function getTitle(): string
    {
        return __('admin.application.actions.create');
    }

    public function form(Schema $schema): Schema
    {
        return CreateApplicationForm::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
