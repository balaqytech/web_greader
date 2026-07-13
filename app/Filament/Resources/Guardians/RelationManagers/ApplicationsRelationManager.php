<?php

namespace App\Filament\Resources\Guardians\RelationManagers;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\Models\Guardian;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.application.plural_label');
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
        /** @var Guardian $guardian */
        $guardian = $this->getOwnerRecord();

        return $table
            ->query(fn (): Builder => $guardian->getApplicationsQuery()->with(['program', 'branch', 'season']))
            ->recordTitleAttribute('ref_no')
            ->columns([
                TextColumn::make('ref_no')
                    ->label(__('admin.application.ref_no'))
                    ->url(fn (Application $record) => ApplicationResource::getUrl('view', ['record' => $record])),
                TextColumn::make('student_name')
                    ->label(__('admin.student.name'))
                    ->placeholder('-'),
                TextColumn::make('program.name')
                    ->label(__('admin.program.name'))
                    ->placeholder('-'),
                TextColumn::make('branch.name')
                    ->label(__('admin.branch.label'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('admin.application.status'))
                    ->badge()
                    ->color(fn (Application $record) => $record->status->getColor())
                    ->formatStateUsing(fn (Application $record) => $record->status->getLabel()),
                TextColumn::make('created_at')
                    ->label(__('admin.lead.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Application $record) => ApplicationResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
