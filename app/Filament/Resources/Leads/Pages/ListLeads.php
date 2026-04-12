<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Actions\Leads\CreateLeadAction;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
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
                    $query->where('status', NewLead::$name);
                }),
            'contacted' => Tab::make('Contacted')->label(__('admin.lead.states.contacted'))
                ->modifyQueryUsing(function ($query) {
                    $query->where('status', ContactedLead::$name);
                }),
            'interested' => Tab::make('Interested')->label(__('admin.lead.states.interested'))
                ->modifyQueryUsing(function ($query) {
                    $query->where('status', Interested::$name);
                }),
            'not_interested' => Tab::make('Not Interested')->label(__('admin.lead.states.not_interested'))
                ->modifyQueryUsing(function ($query) {
                    $query->where('status', NotInterested::$name);
                }),
            'no_response' => Tab::make('No Response')->label(__('admin.lead.states.no_response'))
                ->modifyQueryUsing(function ($query) {
                    $query->where('status', NoResponse::$name);
                }),
        ];
    }
}
