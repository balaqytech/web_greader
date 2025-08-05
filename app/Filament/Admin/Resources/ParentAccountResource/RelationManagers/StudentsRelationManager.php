<?php

namespace App\Filament\Admin\Resources\ParentAccountResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use App\Enums\Gender;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Enums\StudentStatus;
use App\Enums\RelationshipWithParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Admin\Resources\StudentResource;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.parent.students');
    }

    public static function getModelLabel(): string
    {
        return __('admin.student.label');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('branch_id')
                    ->label(__('admin.student.branch_id'))
                    ->required()
                    ->relationship('branch', 'name')
                    ->default(fn(RelationManager $livewire) => $livewire->getOwnerRecord()->branch_id),
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
                    ->default(StudentStatus::PENDING),
                Forms\Components\KeyValue::make('additional_info')
                    ->label(__('admin.student.additional_info'))
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns(StudentResource::table($table)->getColumns())
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn(Model $record): string => StudentResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
