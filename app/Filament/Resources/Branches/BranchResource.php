<?php

namespace App\Filament\Resources\Branches;

use App\Filament\Resources\Branches\Pages\ManageBranches;
use App\Models\Branch;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $recordTitleAttribute = 'name';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.branch.name'))
                    ->required(),
                TextInput::make('address')
                    ->label(__('admin.branch.address'))
                    ->default(null),
                TextInput::make('governorate')
                    ->label(__('admin.branch.governorate'))
                    ->default(null),
                TextInput::make('phone')
                    ->label(__('admin.branch.phone'))
                    ->tel()
                    ->default(null),
                TextInput::make('mobile')
                    ->label(__('admin.branch.mobile'))
                    ->default(null),
                Toggle::make('is_active')
                    ->label(__('admin.branch.is_active'))
                    ->required()
                    ->default(true),
                KeyValue::make('additional_info')
                    ->label(__('admin.branch.additional_info'))
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.branch.name'))
                    ->searchable(),
                TextColumn::make('address')
                    ->label(__('admin.branch.address'))
                    ->searchable(),
                TextColumn::make('governorate')
                    ->label(__('admin.branch.governorate'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('admin.branch.phone'))
                    ->searchable(),
                TextColumn::make('mobile')
                    ->label(__('admin.branch.mobile'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('admin.branch.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('admin.branch.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                // DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBranches::route('/'),
        ];
    }
}
