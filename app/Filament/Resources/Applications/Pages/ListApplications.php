<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\Cancelled;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('admin.application.actions.create')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->label(__('admin.application.states.all')),
            'awaiting_registration_fee' => Tab::make('Awaiting Registration Fee')
                ->label(__('admin.application.states.awaiting_registration_fee'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', AwaitingRegistrationFee::$name)),
            'awaiting_application_completion' => Tab::make('Awaiting Application Completion')
                ->label(__('admin.application.states.awaiting_application_completion'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', AwaitingApplicationCompletion::$name)),
            'awaiting_contract_signature' => Tab::make('Awaiting Contract Signature')
                ->label(__('admin.application.states.awaiting_contract_signature'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', AwaitingContractSignature::$name)),
            'awaiting_branch_review' => Tab::make('Awaiting Branch Review')
                ->label(__('admin.application.states.awaiting_branch_review'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', AwaitingBranchReview::$name)),
            'cancelled' => Tab::make('Cancelled')
                ->label(__('admin.application.states.cancelled'))
                ->modifyQueryUsing(fn ($query) => $query->where('status', Cancelled::$name)),
        ];
    }
}
