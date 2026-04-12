<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Enums\LeadContactMethod;
use App\Enums\LeadContactResult;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('contacted_by')
                    ->label(__('admin.lead.contact.contacted_by'))
                    ->required(),
                Select::make('contact_method')
                    ->label(__('admin.lead.contact.contact_method'))
                    ->options(LeadContactMethod::class)
                    ->required(),
                Select::make('contact_result')
                    ->label(__('admin.lead.contact.contact_result'))
                    ->options(LeadContactResult::class)
                    ->required(),
                Textarea::make('notes')
                    ->label(__('admin.lead.contact.notes'))
                    ->default(null)
                    ->columnSpanFull(),
                DateTimePicker::make('follow_up_at')
                    ->label(__('admin.lead.contact.follow_up_at')),
                DateTimePicker::make('contacted_at')
                    ->label(__('admin.lead.contact.contacted_at'))
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('lead_id')
            ->heading(__('admin.lead.contact.plural_label'))
            ->columns([
                TextColumn::make('contacted_by')
                    ->label(__('admin.lead.contact.contacted_by'))
                    ->searchable(),
                TextColumn::make('contact_method')
                    ->label(__('admin.lead.contact.contact_method'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('contact_result')
                    ->label(__('admin.lead.contact.contact_result'))
                    ->searchable(),
                TextColumn::make('follow_up_at')
                    ->label(__('admin.lead.contact.follow_up_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('contacted_at')
                    ->label(__('admin.lead.contact.contacted_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.lead.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
