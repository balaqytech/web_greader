<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\States\Leads\ContactedLead;
use App\States\Leads\Interested;
use App\States\Leads\NewLead;
use App\States\Leads\NoResponse;
use App\States\Leads\NotInterested;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')->label(__('admin.lead.states.all')),
            'new' => Tab::make('New')->label(__('admin.lead.states.new'))
                ->modifyQueryUsing(function ($query) {
                    $query->whereState('status', NewLead::class);
                }),
            'contacted' => Tab::make('Contacted')->label(__('admin.lead.states.contacted'))
                ->modifyQueryUsing(function ($query) {
                    $query->whereState('status', ContactedLead::class);
                }),
            'interested' => Tab::make('Interested')->label(__('admin.lead.states.interested'))
                ->modifyQueryUsing(function ($query) {
                    $query->whereState('status', Interested::class);
                }),
            'not_interested' => Tab::make('Not Interested')->label(__('admin.lead.states.not_interested'))
                ->modifyQueryUsing(function ($query) {
                    $query->whereState('status', NotInterested::class);
                }),
            'no_response' => Tab::make('No Response')->label(__('admin.lead.states.no_response'))
                ->modifyQueryUsing(function ($query) {
                    $query->whereState('status', NoResponse::class);
                }),
        ];
    }
}
