<?php

namespace App\Filament\Admin\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Student;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Admin\Resources\StudentResource\Pages;
use App\Filament\Admin\Resources\StudentResource\RelationManagers;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.school');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.students');
    }

    public static function getModelLabel(): string
    {
        return __('admin.student.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.student.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('parent_account_id')
                    ->label(__('admin.student.parent_account_id'))
                    ->required()
                    ->relationship('parentAccount', 'name'),
                Forms\Components\Select::make('branch_id')
                    ->label(__('admin.student.branch_id'))
                    ->required()
                    ->relationship('branch', 'name'),
                Forms\Components\TextInput::make('name')
                    ->label(__('admin.student.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('gender')
                    ->label(__('admin.student.gender'))
                    ->required()
                    ->options(Gender::class),
                Forms\Components\DatePicker::make('date_of_birth')
                    ->label(__('admin.student.date_of_birth'))
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label(__('admin.student.status'))
                    ->required()
                    ->options(StudentStatus::class)
                    ->default(StudentStatus::ACTIVE),
                Forms\Components\KeyValue::make('additional_info')
                    ->label(__('admin.student.additional_info'))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('parentAccount.name')
                    ->label(__('admin.student.parent_account_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('admin.student.branch_id'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.student.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender')
                    ->label(__('admin.student.gender'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('date_of_birth')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('admin.student.branch_id'))
                    ->relationship('branch', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.student.status'))
                    ->options(StudentStatus::class),
                Tables\Filters\SelectFilter::make('gender')
                    ->label(__('admin.student.gender'))
                    ->options(Gender::class),
            ])->actions([
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
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
