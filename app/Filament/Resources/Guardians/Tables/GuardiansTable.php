<?php

namespace App\Filament\Resources\Guardians\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GuardiansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.guardian.name'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('admin.guardian.phone'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('admin.guardian.email'))
                    ->searchable(),
                TextColumn::make('id_number')
                    ->label(__('admin.guardian.id_number'))
                    ->searchable(),
                TextColumn::make('occupation')
                    ->label(__('admin.guardian.occupation'))
                    ->searchable(),
                TextColumn::make('work_address')
                    ->label(__('admin.guardian.work_address'))
                    ->searchable(),
                TextColumn::make('work_phone')
                    ->label(__('admin.guardian.work_phone'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('admin.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
