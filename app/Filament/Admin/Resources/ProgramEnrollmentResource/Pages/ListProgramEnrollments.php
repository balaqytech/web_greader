<?php

namespace App\Filament\Admin\Resources\ProgramEnrollmentResource\Pages;

use Filament\Actions;
use App\Enums\EnrollmentStatus;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Admin\Resources\ProgramEnrollmentResource;

class ListProgramEnrollments extends ListRecords
{
    protected ?string $maxContentWidth = 'full';
    protected static string $resource = ProgramEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make(__(EnrollmentStatus::PENDING->getLabel()))
                ->modifyQueryUsing(fn($query) => $query->where('status', EnrollmentStatus::PENDING)),
            'signed' => Tab::make(__(EnrollmentStatus::SIGNED->getLabel()))
                ->modifyQueryUsing(fn($query) => $query->where('status', EnrollmentStatus::SIGNED)),
            'approved' => Tab::make(__(EnrollmentStatus::APPROVED->getLabel()))
                ->modifyQueryUsing(fn($query) => $query->where('status', EnrollmentStatus::APPROVED)),
            'rejected' => Tab::make(__(EnrollmentStatus::REJECTED->getLabel()))
                ->modifyQueryUsing(fn($query) => $query->where('status', EnrollmentStatus::REJECTED)),
            'canceled' => Tab::make(__(EnrollmentStatus::CANCELED->getLabel()))
                ->modifyQueryUsing(fn($query) => $query->where('status', EnrollmentStatus::CANCELED)),
            'completed' => Tab::make(__(EnrollmentStatus::COMPLETED->getLabel()))
                ->modifyQueryUsing(fn($query) => $query->where('status', EnrollmentStatus::COMPLETED)),
        ];
    }
}
