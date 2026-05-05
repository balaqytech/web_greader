<?php

namespace App\Filament\Resources\Students\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.student.name'))
                    ->searchable(),
                TextColumn::make('guardian.name')
                    ->label(__('admin.student.guardian_name'))
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label(__('admin.student.branch_name')),
                TextColumn::make('gender')
                    ->label(__('admin.student.gender'))
                    ->badge(),
                TextColumn::make('birth_date')
                    ->label(__('admin.student.birth_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('civil_number')
                    ->label(__('admin.student.civil_number'))
                    ->searchable(),
                TextColumn::make('state')
                    ->label(__('admin.student.state')),
                TextColumn::make('governorate')
                    ->label(__('admin.student.governorate')),
                TextColumn::make('village')
                    ->label(__('admin.student.village')),
                TextColumn::make('house_number')
                    ->label(__('admin.student.house_number')),
                TextColumn::make('parents_social_status')
                    ->label(__('admin.student.parents_social_status')),
                TextColumn::make('relationship_with_guardian')
                    ->label(__('admin.student.relationship_with_guardian'))
                    ->badge(),
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
