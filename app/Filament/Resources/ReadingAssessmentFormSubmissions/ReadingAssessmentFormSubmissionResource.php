<?php

namespace App\Filament\Resources\ReadingAssessmentFormSubmissions;

use App\Filament\Resources\ReadingAssessmentFormSubmissions\Pages\ManageReadingAssessmentFormSubmissions;
use App\Models\ReadingAssessmentFormSubmission;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReadingAssessmentFormSubmissionResource extends Resource
{
    protected static ?string $model = ReadingAssessmentFormSubmission::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.forms');
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
                TextColumn::make('additional_info')
                    ->label(__('admin.reading_assessment_form_submissions.additional_info'))
                    ->formatStateUsing(fn(Model $record) => collect($record->additional_info)->map(fn($item) => $item['key'] . ': ' . $item['value'])->implode('<br>'))
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
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReadingAssessmentFormSubmissions::route('/'),
        ];
    }
}
