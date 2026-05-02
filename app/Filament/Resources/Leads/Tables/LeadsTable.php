<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\Source;
use App\Models\Lead;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

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
                    ->color(fn (Lead $record) => $record->status->color())
                    ->formatStateUsing(fn (Lead $record) => $record->status->getLabel()),
                TextColumn::make('source')
                    ->label(__('admin.lead.source'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('admin.lead.created_at'))
                    ->dateTime()
                    ->sortable(),
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
                Filter::make('created_at')
                    ->columns(2)
                    ->columnSpan(2)
                    ->schema([
                        DatePicker::make('created_from')
                            ->label(__('admin.lead.created_from')),
                        DatePicker::make('created_until')
                            ->label(__('admin.lead.created_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ], FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                ViewAction::make(),
            ])
            ->headerActions([
                ExportAction::make()->exports([
                    ExcelExport::make('table')
                        ->fromTable()
                        ->withColumns([
                            Column::make('created_at')
                                ->heading(__('admin.lead.created_at')),
                        ])
                        ->withFileName(function ($resource) {
                            return $resource::getNavigationLabel().'-'.now()->format('Y-m-d');
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
