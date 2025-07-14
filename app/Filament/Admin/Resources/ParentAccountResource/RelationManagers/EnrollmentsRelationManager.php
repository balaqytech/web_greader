<?php

namespace App\Filament\Admin\Resources\ParentAccountResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Admin\Resources\EnrollmentResource;
use Filament\Resources\RelationManagers\RelationManager;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.parent.enrollments');
    }

    public static function getModelLabel(): string
    {
        return __('admin.enrollment.label');
    }

    public function table(Table $table): Table
    {
        $columns = collect(EnrollmentResource::table($table)->getColumns())
            ->reject(fn($column) => $column->getName() === 'student.full_name')
            ->prepend(
                Tables\Columns\TextColumn::make('student.name')
                    ->label(__('admin.enrollment.student_name'))
            )
            ->toArray();

        return $table
            ->recordTitleAttribute('contract_pdf')
            ->columns($columns)
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}
