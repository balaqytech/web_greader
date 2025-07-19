<?php

namespace App\Filament\Admin\Resources;

use Filament\Infolists;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Installment;
use App\Enums\InstallmentStatus;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Admin\Resources\InstallmentResource\Pages;
use App\Filament\Admin\Resources\InstallmentResource\RelationManagers;
use Filament\Infolists\Infolist;

class InstallmentResource extends Resource
{
    protected static ?string $model = Installment::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.installments');
    }

    public static function getModelLabel(): string
    {
        return __('admin.installment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.installment.plural_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('program_enrollment.student.full_name')
                    ->label(__('admin.student.student_name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.name')
                    ->label(__('admin.installment.student_name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('admin.installment.amount')),
                Tables\Columns\TextColumn::make('due_date')
                    ->label(__('admin.installment.due_date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_date')
                    ->label(__('admin.installment.paid_date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.installment.status'))
                    ->badge()
                    ->color(fn($state) => $state->color()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(InstallmentStatus::cases())
                    ->label(__('admin.installment.status')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('admin.installment.installment_info'))
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('program_enrollment.student.full_name')
                            ->label(__('admin.student.name')),
                        Infolists\Components\TextEntry::make('amount')
                            ->label(__('admin.installment.amount')),
                        Infolists\Components\TextEntry::make('due_date')
                            ->label(__('admin.installment.due_date'))
                            ->date(),
                        Infolists\Components\TextEntry::make('paid_date')
                            ->label(__('admin.installment.paid_date'))
                            ->date(),
                        Infolists\Components\IconEntry::make('status')
                            ->label(__('admin.installment.status'))
                            ->color(fn($state) => $state->color()),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstallments::route('/'),
            'view' => Pages\ViewInstallment::route('/{record}'),
        ];
    }
}