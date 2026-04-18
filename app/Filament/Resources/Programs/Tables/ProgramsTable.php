<?php

namespace App\Filament\Resources\Programs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProgramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.program.name'))
                    ->searchable(),
                TextColumn::make('description')
                    ->label(__('admin.program.description'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('admin.program.type'))
                    ->badge()
                    ->searchable(),
                IconColumn::make('accept_installments')
                    ->label(__('admin.program.accept_installments'))
                    ->boolean(),
                IconColumn::make('is_open')
                    ->label(__('admin.program.is_open'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('admin.program.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('admin.program.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('sort_order', 'asc');
    }
}
