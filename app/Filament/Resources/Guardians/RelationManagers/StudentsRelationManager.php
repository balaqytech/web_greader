<?php

namespace App\Filament\Resources\Guardians\RelationManagers;

use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Students\Tables\StudentsTable;
use App\Models\Student;
use Filament\Actions\ViewAction;
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
        return StudentsTable::configure($table)
            ->recordActions([
                ViewAction::make()
                    ->url(fn(Student $record) => StudentResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
