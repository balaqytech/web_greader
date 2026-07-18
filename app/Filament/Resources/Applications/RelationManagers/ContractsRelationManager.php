<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use App\Models\ApplicationContract;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
            ->recordActions([
                // Read-only: open the stored signed artifact in a new tab. Never exposes the
                // signing token; visible only when a signed file exists; authorized against the
                // owning application's view policy so cross-branch reviewers are denied. Resolves
                // both legacy absolute URLs and current disk-relative paths via signedFileUrl().
                Action::make('view_signed_contract')
                    ->label(__('admin.application.actions.view_signed_contract'))
                    ->tooltip(__('admin.application.actions.view_signed_contract'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (ApplicationContract $record): ?string => $record->signedFileUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (ApplicationContract $record): bool => $record->file_path !== null)
                    ->authorize(function (ApplicationContract $record): bool {
                        $application = $record->application;

                        return $application !== null && (Auth::user()?->can('view', $application) ?? false);
                    }),
            ])
            ->toolbarActions([]);
    }
}
