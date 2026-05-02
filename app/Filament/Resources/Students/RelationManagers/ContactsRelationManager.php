<?php

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.application.contacts_section');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('type')
                    ->label(__('admin.application_contacts.type_label'))
                    ->badge(),
                TextColumn::make('name')
                    ->label(__('admin.application_contacts.name'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('admin.application_contacts.phone'))
                    ->placeholder('-'),
                TextColumn::make('email')
                    ->label(__('admin.application_contacts.email'))
                    ->placeholder('-'),
                TextColumn::make('id_number')
                    ->label(__('admin.application_contacts.id_number'))
                    ->placeholder('-'),
                TextColumn::make('relationship')
                    ->label(__('admin.application_contacts.relationship'))
                    ->placeholder('-'),
                IconColumn::make('is_guardian')
                    ->label(__('admin.application_contacts.is_guardian'))
                    ->boolean(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
