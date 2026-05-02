<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\States\Applications\Accepted;
use App\States\Applications\Cancelled;
use App\States\Applications\Draft;
use App\States\Applications\Rejected;
use App\States\Applications\Submitted;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContractSignature;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->label(__('admin.application.states.all')),
            'draft' => Tab::make('Draft')
                ->label(__('admin.application.states.draft'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', Draft::$name)),
            'submitted' => Tab::make('Submitted')
                ->label(__('admin.application.states.submitted'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', Submitted::$name)),
            'waiting_contract_signature' => Tab::make('Waiting Contract Signature')
                ->label(__('admin.application.states.waiting_contract_signature'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', WaitingContractSignature::$name)),
            'under_review' => Tab::make('Under Review')
                ->label(__('admin.application.states.under_review'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', UnderReview::$name)),
            'accepted' => Tab::make('Accepted')
                ->label(__('admin.application.states.accepted'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', Accepted::$name)),
            'rejected' => Tab::make('Rejected')
                ->label(__('admin.application.states.rejected'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', Rejected::$name)),
            'cancelled' => Tab::make('Cancelled')
                ->label(__('admin.application.states.cancelled'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', Cancelled::$name)),
        ];
    }
}
