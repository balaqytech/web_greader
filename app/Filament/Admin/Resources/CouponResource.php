<?php

namespace App\Filament\Admin\Resources;

use App\Enums\CouponType;
use App\Filament\Admin\Resources\CouponResource\Pages;
use App\Filament\Admin\Resources\CouponResource\RelationManagers;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.coupons');
    }

    public static function getModelLabel(): string
    {
        return __('admin.coupon.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.coupon.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label(__('admin.coupon.code'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->label(__('admin.coupon.type'))
                    ->required()
                    ->default(CouponType::FIXED->value)
                    ->options(CouponType::class),
                Forms\Components\TextInput::make('value')
                    ->label(__('admin.coupon.value'))
                    ->required()
                    ->numeric(),
                Forms\Components\DateTimePicker::make('valid_from')
                    ->label(__('admin.coupon.valid_from'))
                    ->required(),
                Forms\Components\DateTimePicker::make('valid_to')
                    ->label(__('admin.coupon.valid_to'))
                    ->required(),
                Forms\Components\TextInput::make('usage_limit')
                    ->label(__('admin.coupon.usage_limit'))
                    ->numeric(),
                Forms\Components\Select::make('applicable_program_id')
                    ->label(__('admin.coupon.applicable_program_id'))
                    ->relationship('program', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('admin.coupon.code'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.coupon.type')),
                Tables\Columns\TextColumn::make('value')
                    ->label(__('admin.coupon.value'))
                    ->money('OMR'),
                Tables\Columns\TextColumn::make('valid_from')
                    ->label(__('admin.coupon.valid_from'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_to')
                    ->label(__('admin.coupon.valid_to'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage_limit')
                    ->label(__('admin.coupon.usage_limit')),
                Tables\Columns\TextColumn::make('usage_count')
                    ->label(__('admin.coupon.usage_count')),
                Tables\Columns\TextColumn::make('program.name')
                    ->label(__('admin.coupon.applicable_program_id')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('admin.coupon.type'))
                    ->options(CouponType::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCoupons::route('/'),
        ];
    }
}
