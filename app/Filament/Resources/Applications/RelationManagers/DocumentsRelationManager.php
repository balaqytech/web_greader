<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use App\Actions\Documents\ApproveDocumentAction;
use App\Actions\Documents\RejectDocumentAction;
use App\Actions\Documents\UploadDocumentAction;
use App\DTOs\Documents\UploadDocumentDTO;
use App\Enums\DocumentType;
use App\Models\ApplicationDocument;
use App\States\Documents\DocumentState;
use App\States\Documents\Uploaded;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Read/upload/review surface for an application's document requirements. There is deliberately
 * no generic create/edit/delete: requirements are materialised by the sync action, files are
 * append-only, and every mutation goes through an authoritative action. Each action carries
 * both a presentation-level `visible()` guard and a `Gate::authorize()` inside its callback,
 * so a forged Livewire call is refused even if the control was never rendered.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.document.plural_label');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->modifyQueryUsing(fn ($query) => $query->withCount('files'))
            ->columns([
                TextColumn::make('type')
                    ->label(__('admin.document.type'))
                    ->badge()
                    ->formatStateUsing(fn (DocumentType $state): string => $state->getLabel()),
                TextColumn::make('requirement_group')
                    ->label(__('admin.document.requirement_group'))
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : __("admin.document.groups.{$state}")),
                IconColumn::make('is_required')
                    ->label(__('admin.document.is_required'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('admin.document.status'))
                    ->badge()
                    ->formatStateUsing(fn (DocumentState $state): string => $state->getLabel())
                    ->color(fn (DocumentState $state): string => $state->getColor()),
                TextColumn::make('currentFile.original_name')
                    ->label(__('admin.document.current_file'))
                    ->placeholder('-'),
                TextColumn::make('currentFile.size')
                    ->label(__('admin.document.file_size'))
                    ->placeholder('-')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : $this->formatBytes($state)),
                TextColumn::make('reviewer.name')
                    ->label(__('admin.document.reviewed_by'))
                    ->placeholder('-'),
                TextColumn::make('rejection_reason')
                    ->label(__('admin.document.rejection_reason'))
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('files_count')
                    ->label(__('admin.document.upload_count'))
                    ->badge(),
            ])
            ->recordActions([
                $this->uploadAction(),
                $this->approveAction(),
                $this->rejectAction(),
                $this->downloadCurrentAction(),
                $this->historyAction(),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }

    private function uploadAction(): Action
    {
        return Action::make('upload')
            ->label(__('admin.document.actions.upload'))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->visible(fn (ApplicationDocument $record): bool => Gate::allows('upload', $record))
            ->schema([
                FileUpload::make('file')
                    ->label(__('admin.document.label'))
                    ->disk('local')
                    ->directory('documents/tmp')
                    ->visibility('private')
                    ->required()
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(5120)
                    ->storeFileNamesIn('original_name'),
            ])
            ->action(function (ApplicationDocument $record, array $data): void {
                Gate::authorize('upload', $record);

                $path = $data['file'];

                try {
                    app(UploadDocumentAction::class)->execute(new UploadDocumentDTO(
                        document: $record,
                        temporaryPath: $path,
                        originalName: $data['original_name'] ?? basename($path),
                        uploadedBy: Auth::user(),
                    ));

                    Notification::make()
                        ->title(__('admin.document.messages.upload_success'))
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title(__('admin.document.messages.upload_failed'))
                        ->body($this->messageFor($e))
                        ->danger()
                        ->send();
                }
            });
    }

    private function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('admin.document.actions.approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (ApplicationDocument $record): bool => $record->status instanceof Uploaded && Gate::allows('review', $record))
            ->action(function (ApplicationDocument $record): void {
                Gate::authorize('review', $record);

                try {
                    app(ApproveDocumentAction::class)->execute($record, Auth::user());

                    Notification::make()
                        ->title(__('admin.document.messages.approve_success'))
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title($this->messageFor($e))
                        ->danger()
                        ->send();
                }
            });
    }

    private function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('admin.document.actions.reject'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (ApplicationDocument $record): bool => $record->status instanceof Uploaded && Gate::allows('review', $record))
            ->schema([
                Textarea::make('rejection_reason')
                    ->label(__('admin.document.rejection_reason'))
                    ->required()
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (ApplicationDocument $record, array $data): void {
                Gate::authorize('review', $record);

                try {
                    app(RejectDocumentAction::class)->execute($record, Auth::user(), $data['rejection_reason']);

                    Notification::make()
                        ->title(__('admin.document.messages.reject_success'))
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title($this->messageFor($e))
                        ->danger()
                        ->send();
                }
            });
    }

    private function downloadCurrentAction(): Action
    {
        return Action::make('download')
            ->label(__('admin.document.actions.download'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (ApplicationDocument $record): bool => $record->current_file_id !== null && Gate::allows('view', $record))
            ->url(fn (ApplicationDocument $record): ?string => $record->current_file_id === null
                ? null
                : route('application-documents.files.download', ['file' => $record->current_file_id]))
            ->openUrlInNewTab();
    }

    private function historyAction(): Action
    {
        return Action::make('history')
            ->label(__('admin.document.actions.view_history'))
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->modalHeading(__('admin.document.history'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.document.actions.close'))
            ->visible(fn (ApplicationDocument $record): bool => Gate::allows('view', $record))
            ->modalContent(fn (ApplicationDocument $record) => view('filament.documents.history', [
                'files' => $record->files()->with('uploadedBy')->get(),
            ]));
    }

    private function messageFor(\Throwable $e): string
    {
        $key = property_exists($e, 'translationKey') ? $e->translationKey : null;

        return $key !== null ? __($key) : $e->getMessage();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
