<?php

namespace App\Filament\Admin\Resources;

use App\Enums\ProgramPaymentType;
use App\Enums\ProgramType;
use App\Filament\Admin\Resources\ProgramResource\Pages;
use App\Filament\Admin\Resources\ProgramResource\RelationManagers;
use App\Models\Program;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProgramResource extends Resource
{
    protected static ?string $model = Program::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.school');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.programs');
    }

    public static function getModelLabel(): string
    {
        return __('admin.program.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.program.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('admin.program.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->label(__('admin.program.type'))
                    ->options(ProgramType::class)
                    ->required(),
                Forms\Components\TextInput::make('base_price')
                    ->label(__('admin.program.base_price'))
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\Select::make('payment_type')
                    ->label(__('admin.program.payment_type'))
                    ->options(ProgramPaymentType::class)
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label(__('admin.program.description'))
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('admin.program.is_active'))
                    ->required()
                    ->default(true)
                    ->inline(false),
                Forms\Components\KeyValue::make('additional_info')
                    ->label(__('admin.program.additional_info'))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.program.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.program.type'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label(__('admin.program.base_price'))
                    ->formatStateUsing(fn($state) => \Illuminate\Support\Number::currency($state, 'OMR', app()->getLocale())),
                Tables\Columns\TextColumn::make('payment_type')
                    ->label(__('admin.program.payment_type'))
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.program.is_active'))
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrograms::route('/'),
            'create' => Pages\CreateProgram::route('/create'),
            'edit' => Pages\EditProgram::route('/{record}/edit'),
        ];
    }
}
