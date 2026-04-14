<?php

namespace App\Filament\Resources\ReadingAssessmentFormSubmissions\Pages;

use App\Filament\Resources\ReadingAssessmentFormSubmissions\ReadingAssessmentFormSubmissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReadingAssessmentFormSubmissions extends ManageRecords
{
    protected static string $resource = ReadingAssessmentFormSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
