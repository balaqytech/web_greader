<?php

namespace App\Filament\Admin\Resources\EnrollmentResource\Pages;

use App\Filament\Admin\Resources\EnrollmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEnrollments extends ListRecords
{
    protected static string $resource = EnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
