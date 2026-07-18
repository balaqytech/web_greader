<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only correction history (§4/§6). Corrections are part of the admission record — no
 * generic create/edit/delete here; they are raised and completed only through the correction
 * workflow actions. The current checklist is shown inline per row.
 */
class CorrectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'corrections';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.application.correction_history');
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
            ->recordTitleAttribute('reason')
            ->columns([
                TextColumn::make('reason')
                    ->label(__('admin.application.correction_reason'))
                    ->wrap(),
                TextColumn::make('checklist')
                    ->label(__('admin.application.correction_checklist'))
                    ->formatStateUsing(function ($state): string {
                        $items = is_array($state) ? $state : [];

                        return collect($items)
                            ->map(fn (array $entry): string => ($entry['done'] ?? false ? '☑' : '☐').' '.($entry['item'] ?? ''))
                            ->implode("\n");
                    })
                    ->wrap(),
                IconColumn::make('is_contract_relevant')
                    ->label(__('admin.application.correction_is_contract_relevant'))
                    ->boolean()
                    ->placeholder('-'),
                TextColumn::make('completed_at')
                    ->label(__('admin.application.correction_completed_at'))
                    ->dateTime()
                    ->placeholder(__('admin.application.correction_status_open')),
                TextColumn::make('requestedBy.name')
                    ->label(__('admin.application.correction_requested_by'))
                    ->placeholder(__('admin.application.activity_system')),
                TextColumn::make('requested_at')
                    ->label(__('admin.application.correction_requested_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
