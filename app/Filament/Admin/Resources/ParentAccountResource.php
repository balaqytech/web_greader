<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ParentAccountResource\Pages;
use App\Filament\Admin\Resources\ParentAccountResource\RelationManagers;
use App\Models\ParentAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ParentAccountResource extends Resource
{
    protected static ?string $model = ParentAccount::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.school');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.parents');
    }

    public static function getModelLabel(): string
    {
        return __('admin.parent.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.parent.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('admin.parent.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label(__('admin.parent.email'))
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label(__('admin.parent.phone'))
                    ->tel()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->label(__('admin.parent.password'))
                    ->password()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('admin.parent.is_active'))
                    ->required()
                    ->default(true)
                    ->inline(false),
                Forms\Components\Select::make('branch_id')
                    ->label(__('admin.parent.branch_id'))
                    ->relationship('branch', 'name')
                    ->required(),
                Forms\Components\KeyValue::make('additional_info')
                    ->label(__('admin.parent.additional_info'))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.parent.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('admin.parent.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('admin.parent.phone'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('childrenCount')
                    ->label(__('admin.parent.children_count')),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.parent.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('admin.parent.branch_id')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('admin.parent.branch_id'))
                    ->relationship('branch', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('admin.parent.parent_info'))
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label(__('admin.parent.name')),
                        Infolists\Components\TextEntry::make('email')
                            ->label(__('admin.parent.email')),
                        Infolists\Components\TextEntry::make('phone')
                            ->label(__('admin.parent.phone')),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label(__('admin.parent.is_active')),
                        Infolists\Components\TextEntry::make('branch.name')
                            ->label(__('admin.parent.branch_id')),
                    ]),
                Infolists\Components\Section::make(__('admin.parent.additional_info'))
                    ->schema(fn($record) => collect($record->additional_info ?? [])
                        ->map(fn($value, $key) => Infolists\Components\TextEntry::make($key)
                            ->label(ucfirst($key))
                            ->state($value))
                        ->toArray())
                    ->columns(1),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StudentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParentAccounts::route('/'),
            'create' => Pages\CreateParentAccount::route('/create'),
            'view' => Pages\ViewParentAccount::route('/{record}'),
            'edit' => Pages\EditParentAccount::route('/{record}/edit'),
        ];
    }
}