<?php

namespace App\Filament\Resources\Affiliates\Tables;

use App\Enums\AffiliateCategory;
use App\Enums\Source;
use App\States\Affiliates\AffiliateState;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.affiliate.name'))
                    ->searchable(),
                TextColumn::make('code')
                    ->label(__('admin.affiliate.code'))
                    ->searchable(),
                TextColumn::make('category')
                    ->label(__('admin.affiliate.category'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('whatsapp')
                    ->label(__('admin.affiliate.whatsapp'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('admin.affiliate.email'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('admin.affiliate.status'))
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->formatStateUsing(fn ($state) => $state->getLabel())
                    ->searchable(),
                TextColumn::make('verified_by')
                    ->label(__('admin.affiliate.verified_by'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('verified_at')
                    ->label(__('admin.affiliate.verified_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rejected_by')
                    ->label(__('admin.affiliate.rejected_by'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rejected_at')
                    ->label(__('admin.affiliate.rejected_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creation_source')
                    ->label(__('admin.affiliate.creation_source'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('admin.lead.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('admin.affiliate.category'))
                    ->options(AffiliateCategory::class),
                SelectFilter::make('status')
                    ->label(__('admin.affiliate.status'))
                    ->options(AffiliateState::all()->mapWithKeys(fn ($state) => [
                        $state::getMorphClass() => $state::getLabel(),
                    ])),
                SelectFilter::make('creation_source')
                    ->label(__('admin.affiliate.creation_source'))
                    ->options(Source::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
