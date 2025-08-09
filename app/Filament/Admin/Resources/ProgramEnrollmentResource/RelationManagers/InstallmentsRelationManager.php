<?php

namespace App\Filament\Admin\Resources\ProgramEnrollmentResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\States\Enrollment\Approved;
use App\States\Enrollment\Completed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.program_enrollment.installments');
    }

    public static function getModelLabel(): string
    {
        return __('admin.installment.label');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('admin.program_enrollment.installment_amount')),
                Tables\Columns\TextColumn::make('due_date')
                    ->label(__('admin.program_enrollment.installment_due_date'))
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.program_enrollment.installment_status'))
                    ->badge()
                    ->color(fn($state) => $state->color()),
            ])
            ->filters([
                //
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->status->equals(Approved::class) || $ownerRecord->status->equals(Completed::class);
    }
}
