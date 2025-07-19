<?php

namespace App\Filament\Admin\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Addon;
use App\Enums\AddonType;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Admin\Resources\AddonResource\Pages;
use App\Filament\Admin\Resources\AddonResource\RelationManagers;

class AddonResource extends Resource
{
    protected static ?string $model = Addon::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.school');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.addons');
    }

    public static function getModelLabel(): string
    {
        return __('admin.addon.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.addon.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('admin.addon.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('price')
                    ->label(__('admin.addon.price'))
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\Textarea::make('description')
                    ->label(__('admin.addon.description'))
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Select::make('type')
                    ->label(__('admin.addon.type'))
                    ->required()
                    ->options(AddonType::class),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('admin.addon.is_active'))
                    ->required()
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.addon.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('admin.addon.price')),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.addon.type'))
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.addon.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAddons::route('/'),
        ];
    }
}