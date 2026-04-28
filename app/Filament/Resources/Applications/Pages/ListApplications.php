<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\States\Applications\Accepted;
use App\States\Applications\DataComplete;
use App\States\Applications\PendingRegistration;
use App\States\Applications\Rejected;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContract;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->label(__('admin.application.states.all')),
            'pending_registration' => Tab::make('Pending Registration')
                ->label(__('admin.application.states.pending_registration'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', PendingRegistration::$name)),
            'data_complete' => Tab::make('Data Complete')
                ->label(__('admin.application.states.data_complete'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', DataComplete::$name)),
            'waiting_contract' => Tab::make('Waiting Contract')
                ->label(__('admin.application.states.waiting_contract'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', WaitingContract::$name)),
            'under_review' => Tab::make('Under Review')
                ->label(__('admin.application.states.under_review'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', UnderReview::$name)),
            'accepted' => Tab::make('Accepted')
                ->label(__('admin.application.states.accepted'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', Accepted::$name)),
            'rejected' => Tab::make('Rejected')
                ->label(__('admin.application.states.rejected'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', Rejected::$name)),
        ];
    }
}
