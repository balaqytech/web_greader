<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DiscountResource\Pages;
use App\Filament\Admin\Resources\DiscountResource\RelationManagers;
use App\Models\Discount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Number;

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.discounts');
    }

    public static function getModelLabel(): string
    {
        return __('admin.discount.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.discount.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('admin.discount.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->label(__('admin.discount.type'))
                    ->required()
                    ->options(\App\Enums\DiscountType::class)
                    ->live(),
                Forms\Components\TextInput::make('amount')
                    ->label(__('admin.discount.amount'))
                    ->required()
                    ->numeric()
                    ->suffix(fn(Get $get) => $get('type') === \App\Enums\DiscountType::PERCENTAGE->value ? '%' : config('app.currency'))
                    ->minValue(0.1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.discount.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.discount.type')),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('admin.discount.amount'))
                    ->formatStateUsing(
                        fn($record, $state) => $record->type === \App\Enums\DiscountType::PERCENTAGE
                            ? Number::format($state) . ' %'
                            : Number::currency($state, config('app.currency'), config('app.locale'))
                    ),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDiscounts::route('/'),
        ];
    }
}
