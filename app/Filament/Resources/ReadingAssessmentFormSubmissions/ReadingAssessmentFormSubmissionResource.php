<?php

namespace App\Filament\Resources\ReadingAssessmentFormSubmissions;

use App\Enums\SubmissionStatus;
use App\Filament\Resources\ReadingAssessmentFormSubmissions\Pages\ManageReadingAssessmentFormSubmissions;
use App\Models\ReadingAssessmentFormSubmission;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReadingAssessmentFormSubmissionResource extends Resource
{
    protected static ?string $model = ReadingAssessmentFormSubmission::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.registration_and_addmission');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.reading_assessment_form_submissions');
    }

    public static function getModelLabel(): string
    {
        return __('admin.reading_assessment_form_submissions.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.reading_assessment_form_submissions.plural_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_name')
                    ->label(__('admin.reading_assessment_form_submissions.student_name')),
                TextColumn::make('age')
                    ->label(__('admin.reading_assessment_form_submissions.age')),
                TextColumn::make('grade_level')
                    ->label(__('admin.reading_assessment_form_submissions.grade_level')),
                TextColumn::make('guardian_name')
                    ->label(__('admin.reading_assessment_form_submissions.guardian_name')),
                TextColumn::make('whatsapp')
                    ->label(__('admin.reading_assessment_form_submissions.whatsapp')),
                TextColumn::make('branch.name')
                    ->label(__('admin.reading_assessment_form_submissions.branch')),
                TextColumn::make('status')
                    ->label(__('admin.reading_assessment_form_submissions.status'))
                    ->badge()
                    ->color(fn(Model $record): string => $record->status->color()),
                TextColumn::make('source')
                    ->label(__('admin.reading_assessment_form_submissions.source'))
                    ->badge(),
                TextColumn::make('additional_info')
                    ->label(__('admin.reading_assessment_form_submissions.additional_info'))
                    ->formatStateUsing(fn(Model $record) => collect($record->additional_info)->each(fn($item, $key) => $key . ': ' . $item)->implode('<br>'))
                    ->html()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('admin.reading_assessment_form_submissions.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('change_status')
                    ->label(__('admin.reading_assessment_form_submissions.change_status'))
                    ->icon('heroicon-o-pencil')
                    ->color('primary')
                    ->modalWidth('md')
                    ->schema([
                        Select::make('status')
                            ->label(__('admin.reading_assessment_form_submissions.status'))
                            ->options(SubmissionStatus::class),
                    ])
                    ->action(function (ReadingAssessmentFormSubmission $record, array $data): void {
                        $record->update($data);
                    }),
            ])
            ->headerActions([
                \pxlrbt\FilamentExcel\Actions\ExportAction::make()->exports([
                    \pxlrbt\FilamentExcel\Exports\ExcelExport::make('table')
                        ->fromTable()
                        ->withFileName(function ($resource) {
                            return $resource::getNavigationLabel() . '-' . now()->format('Y-m-d');
                        }),
                ])
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReadingAssessmentFormSubmissions::route('/'),
        ];
    }
}