<?php

namespace App\Filament\Resources\Guardians\RelationManagers;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.student.plural_label');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.student.name'))
                    ->url(fn (Student $record) => StudentResource::getUrl('view', ['record' => $record]))
                    ->searchable(),
                TextColumn::make('civil_number')
                    ->label(__('admin.student.civil_number'))
                    ->searchable(),
                TextColumn::make('gender')
                    ->label(__('admin.student.gender'))
                    ->badge(),
                TextColumn::make('birth_date')
                    ->label(__('admin.student.birth_date'))
                    ->date(),
                TextColumn::make('created_at')
                    ->label(__('admin.student.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
