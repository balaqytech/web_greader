<?php

namespace App\Filament\Admin\Resources\ParentAccountResource\RelationManagers;

use App\Filament\Admin\Resources\StudentResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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

    public function table(Table $table): Table
    {
        $columns = collect(StudentResource::table($table)->getColumns())
            ->reject(function ($column) {
                return $column->getName() === 'full_name';
            })
            ->prepend(
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.student.name'))
                    ->searchable(),
                'name'
            )
            ->toArray();

        return $table
            ->recordTitleAttribute('name')
            ->columns($columns)
            ->headerActions([
                // Tables\Actions\CreateAction::make()
                // ->url(fn(): string => StudentResource::getUrl('create')),
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
