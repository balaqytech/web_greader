<?php

namespace App\Filament\Resources\ReadingAssessmentFormSubmissions\Pages;

use App\Filament\Resources\ReadingAssessmentFormSubmissions\ReadingAssessmentFormSubmissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageReadingAssessmentFormSubmissions extends ManageRecords
{
    protected static string $resource = ReadingAssessmentFormSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('admin.submission_statuses.all')),
            'new' => Tab::make(__('admin.submission_statuses.new'))
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'new')),
            'pending' => Tab::make(__('admin.submission_statuses.pending'))
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'pending')),
            'processing' => Tab::make(__('admin.submission_statuses.processing'))
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'processing')),
            'completed' => Tab::make(__('admin.submission_statuses.completed'))
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'completed')),
        ];
    }
}
