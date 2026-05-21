<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Actions\Leads\TransitionLeadStateAction;
use App\Enums\LeadContactMethod;
use App\Filament\Resources\Leads\LeadResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('transition_state')
                ->label(__('admin.lead.transition_status_to'))
                ->color('primary')
                ->icon('heroicon-o-arrow-right')
                ->visible(function ($record) {
                    return $record->status->hasTransitionableStates();
                })
                ->schema([
                    Select::make('to_status')
                        ->label(__('admin.lead.to_status'))
                        ->options(function () {
                            return collect($this->record->status->transitionableStateInstances())
                                ->mapWithKeys(fn ($state) => [$state::class => $state->getLabel()]);
                        })
                        ->required(),
                    Select::make('contactMethod')
                        ->label(__('admin.lead.contact_method'))
                        ->options(LeadContactMethod::class)
                        ->required(),
                    Textarea::make('notes')
                        ->label(__('admin.lead.notes')),
                ])
                ->action(function (TransitionLeadStateAction $transition_action, array $data, $record) {
                    $transition_action->execute(
                        $data['to_status'],
                        $record,
                        Auth::user()->name,
                        $data['contactMethod'],
                        $data['notes'] ?? null
                    );
                }),
        ];
    }
}
