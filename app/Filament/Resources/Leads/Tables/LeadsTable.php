<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Models\Lead;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ref_no')
                    ->label(__('admin.lead.ref_no'))
                    ->searchable(),
                TextColumn::make('student_name')
                    ->label(__('admin.lead.student_name'))
                    ->searchable(),
                TextColumn::make('guardian_name')
                    ->label(__('admin.lead.guardian_name'))
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label(__('admin.branch.label'))
                    ->searchable(),
                TextColumn::make('program_type')
                    ->label(__('admin.lead.program_type'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('program.name')
                    ->label(__('admin.program.label'))
                    ->searchable(),
                TextColumn::make('whatsapp')
                    ->label(__('admin.lead.whatsapp'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('admin.lead.status'))
                    ->searchable()
                    ->badge()
                    ->color(fn(Lead $record) => $record->status->color())
                    ->formatStateUsing(fn(Lead $record) => $record->status->getLabel()),
                TextColumn::make('source')
                    ->label(__('admin.lead.source'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('admin.lead.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
