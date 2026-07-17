<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only history of contract versions (§5.5). Versions are immutable records — there is no
 * generic create/edit/delete here; they are produced only by the generation/supersession/
 * signing workflow.
 */
class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.application.contract_history');
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
            ->recordTitleAttribute('version')
            ->columns([
                TextColumn::make('version')
                    ->label(__('admin.application.contract_version'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('admin.application.contract_status'))
                    ->badge(),
                IconColumn::make('signed_by_applicant')
                    ->label(__('admin.application.contract_signed_by'))
                    ->boolean(),
                TextColumn::make('signed_at')
                    ->label(__('admin.application.contract_signed_at'))
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('generatedBy.name')
                    ->label(__('admin.application.contract_generated_by'))
                    ->placeholder(__('admin.application.activity_system')),
                TextColumn::make('created_at')
                    ->label(__('admin.application.contract_generated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('version', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
