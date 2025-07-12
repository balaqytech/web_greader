<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AcademicYearResource\Pages;
use App\Filament\Admin\Resources\AcademicYearResource\RelationManagers;
use App\Models\AcademicYear;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AcademicYearResource extends Resource
{
    protected static ?string $model = AcademicYear::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.academic_years');
    }

    public static function getModelLabel(): string
    {
        return __('admin.academic_year.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.academic_year.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('admin.academic_year.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('start_date')
                    ->label(__('admin.academic_year.start_date')),
                Forms\Components\DatePicker::make('end_date')
                    ->label(__('admin.academic_year.end_date')),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('admin.academic_year.is_active'))
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
                    ->label(__('admin.academic_year.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('admin.academic_year.start_date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label(__('admin.academic_year.end_date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.academic_year.is_active'))
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
            'index' => Pages\ManageAcademicYears::route('/'),
        ];
    }
}
