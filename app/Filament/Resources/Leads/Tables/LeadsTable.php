<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\Source;
use App\Models\Lead;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->label(__('admin.lead.program'))
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
                    ->badge()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('admin.lead.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('program_id')
                    ->label(__('admin.lead.program'))
                    ->relationship('program', 'name'),
                SelectFilter::make('branch_id')
                    ->label(__('admin.lead.branch'))
                    ->relationship('branch', 'name'),
                SelectFilter::make('source')
                    ->label(__('admin.lead.source'))
                    ->options(Source::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->headerActions([
                \pxlrbt\FilamentExcel\Actions\ExportAction::make()->exports([
                    \pxlrbt\FilamentExcel\Exports\ExcelExport::make('table')
                        ->fromTable()
                        ->withFileName(function ($resource) {
                            return $resource::getNavigationLabel() . '-' . now()->format('Y-m-d');
                        }),
                ])
            ])
            ->defaultSort('created_at', 'desc');
    }
}
