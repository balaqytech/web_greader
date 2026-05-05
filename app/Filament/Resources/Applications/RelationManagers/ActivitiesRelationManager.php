<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.application.activity');
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
            ->recordTitleAttribute('transitioned_at')
            ->columns([
                TextColumn::make('from_state')
                    ->label(__('admin.application.activity_from'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("admin.application.states.{$state}")),
                TextColumn::make('to_state')
                    ->label(__('admin.application.activity_to'))
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (string $state) => __("admin.application.states.{$state}")),
                TextColumn::make('transitionedBy.name')
                    ->label(__('admin.application.activity_by'))
                    ->placeholder(__('admin.application.activity_system'))
                    ->icon('heroicon-m-user'),
                TextColumn::make('notes')
                    ->label(__('admin.application.notes'))
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('transitioned_at')
                    ->label(__('admin.application.activity_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('transitioned_at', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
