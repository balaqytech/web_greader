<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BranchResource\Pages;
use App\Filament\Admin\Resources\BranchResource\RelationManagers;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.branches');
    }

    public static function getModelLabel(): string
    {
        return __('admin.branch.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.branch.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('admin.branch.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('address')
                    ->label(__('admin.branch.address'))
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label(__('admin.branch.phone'))
                    ->tel()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('admin.branch.is_active'))
                    ->default(true)
                    ->inline(false)
                    ->required(),
                Forms\Components\KeyValue::make('additional_info')
                    ->label(__('admin.branch.additional_info'))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label(__('admin.branch.name')),
                Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->label(__('admin.branch.address')),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->label(__('admin.branch.phone')),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('admin.branch.is_active')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBranches::route('/'),
        ];
    }
}
