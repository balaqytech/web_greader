<?php

namespace App\Filament\Resources\Applications\Tables;

use App\Models\Application;
use App\States\Applications\Accepted;
use App\States\Applications\Cancelled;
use App\States\Applications\Draft;
use App\States\Applications\Rejected;
use App\States\Applications\Submitted;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContractSignature;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'applicationStudent',
                'guardianContact',
                'program',
                'branch',
                'season',
            ]))
            ->columns([
                TextColumn::make('ref_no')
                    ->label(__('admin.application.ref_no'))
                    ->searchable(),
                TextColumn::make('applicationStudent.name')
                    ->label(__('admin.student.name'))
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('guardianContact.name')
                    ->label(__('admin.guardian.name'))
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('guardianContact.phone')
                    ->label(__('admin.guardian.phone'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('branch.name')
                    ->label(__('admin.branch.label'))
                    ->searchable(),
                TextColumn::make('season.name')
                    ->label(__('admin.season.label'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('program.name')
                    ->label(__('admin.program.name'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('admin.application.status'))
                    ->badge()
                    ->color(fn (Application $record) => $record->status->getColor())
                    ->formatStateUsing(fn (Application $record) => $record->status->getLabel()),
                TextColumn::make('created_at')
                    ->label(__('admin.lead.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label(__('admin.branch.label'))
                    ->relationship('branch', 'name'),
                SelectFilter::make('season_id')
                    ->label(__('admin.season.label'))
                    ->relationship('season', 'name'),
                SelectFilter::make('program_id')
                    ->label(__('admin.program.name'))
                    ->relationship('program', 'name'),
                SelectFilter::make('status')
                    ->label(__('admin.application.status'))
                    ->options([
                        Draft::$name => __('admin.application.states.draft'),
                        Submitted::$name => __('admin.application.states.submitted'),
                        WaitingContractSignature::$name => __('admin.application.states.waiting_contract_signature'),
                        UnderReview::$name => __('admin.application.states.under_review'),
                        Accepted::$name => __('admin.application.states.accepted'),
                        Rejected::$name => __('admin.application.states.rejected'),
                        Cancelled::$name => __('admin.application.states.cancelled'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
