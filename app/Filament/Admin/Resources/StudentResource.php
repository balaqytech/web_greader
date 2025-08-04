<?php

namespace App\Filament\Admin\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Enums\Gender;
use App\Models\Student;
use Filament\Infolists;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Enums\StudentStatus;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use App\Enums\RelationshipWithParent;
use Illuminate\Database\Eloquent\Model;
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
                    ->searchable()
                    ->preload()
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
                Forms\Components\Select::make('relationship_with_parent')
                    ->label(__('admin.student.relationship_with_parent'))
                    ->required()
                    ->options(RelationshipWithParent::class),
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
                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('admin.student.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('admin.student.branch_id'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender')
                    ->label(__('admin.student.gender'))
                    ->badge()
                    ->color(fn($state) => $state->color()),
                Tables\Columns\TextColumn::make('date_of_birth')
                    ->label(__('admin.student.date_of_birth'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('relationship_with_parent')
                    ->label(__('admin.student.relationship_with_parent'))
                    ->badge()
                    ->color(fn($state) => $state->color()),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.student.status'))
                    ->badge()
                    ->color(fn($state) => $state->color()),
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('admin.student.student_info'))
                    ->schema([
                        Infolists\Components\TextEntry::make('full_name')
                            ->label(__('admin.student.name')),
                        Infolists\Components\TextEntry::make('branch.name')
                            ->label(__('admin.student.branch_id')),
                        Infolists\Components\TextEntry::make('gender')
                            ->label(__('admin.student.gender'))
                            ->badge()
                            ->color(fn($state) => $state->color()),
                        Infolists\Components\TextEntry::make('date_of_birth')
                            ->label(__('admin.student.date_of_birth'))
                            ->date(),
                        Infolists\Components\TextEntry::make('relationship_with_parent')
                            ->label(__('admin.student.relationship_with_parent'))
                            ->badge()
                            ->color(fn($state) => $state->color()),
                        Infolists\Components\TextEntry::make('status')
                            ->label(__('admin.student.status'))
                            ->badge()
                            ->color(fn($state) => $state->color()),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make(__('admin.student.additional_info'))
                    ->schema(
                        fn(Model $record) => collect($record->additional_info)
                            ->map(function ($value, $key) {
                                return Infolists\Components\TextEntry::make($key)
                                    ->label(ucfirst(str_replace('_', ' ', $key)))
                                    ->value($value);
                            })
                            ->toArray()
                    )
                    ->columns(2),
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
            'view' => Pages\ViewStudent::route('/{record}'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}