<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\Applications\SubmitApplicationForReviewAction;
use App\Actions\Applications\UpdateApplicationDataAction;
use App\DTOs\Application\UpdateApplicationDataDTO;
use App\Enums\Gender;
use App\Models\Application;
use App\States\Applications\PendingRegistration;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SubmitForReviewFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'submit_for_review';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.application.actions.submit_for_review'));
        $this->icon('heroicon-o-paper-airplane');
        $this->color('primary');
        $this->modal();
        $this->slideOver();
        $this->modalHeading(__('admin.application.actions.submit_for_review'));
        $this->modalSubmitActionLabel(__('admin.application.actions.submit_for_review'));

        $this->fillForm(fn (Application $record): array => [
            'student_name' => $record->student_name,
            'student_gender' => $record->student_gender?->value,
            'student_birth_date' => $record->student_birth_date?->toDateString(),
            'student_civil_number' => $record->student_civil_number,
            'student_state' => $record->student_state,
            'student_governorate' => $record->student_governorate,
            'student_village' => $record->student_village,
            'student_house_number' => $record->student_house_number,
            'student_parents_social_status' => $record->student_parents_social_status,

            'father_name' => $record->father_name,
            'father_phone' => $record->father_phone,
            'father_email' => $record->father_email,
            'father_id_number' => $record->father_id_number,
            'father_occupation' => $record->father_occupation,
            'father_work_address' => $record->father_work_address,
            'father_work_phone' => $record->father_work_phone,
            'father_is_guardian' => $record->father_is_guardian,

            'mother_name' => $record->mother_name,
            'mother_phone' => $record->mother_phone,
            'mother_email' => $record->mother_email,
            'mother_id_number' => $record->mother_id_number,
            'mother_occupation' => $record->mother_occupation,
            'mother_work_address' => $record->mother_work_address,
            'mother_work_phone' => $record->mother_work_phone,
            'mother_is_guardian' => $record->mother_is_guardian,

            'relative_name' => $record->relative_name,
            'relative_phone' => $record->relative_phone,
            'relative_email' => $record->relative_email,
            'relative_id_number' => $record->relative_id_number,
            'relative_occupation' => $record->relative_occupation,
            'relative_work_address' => $record->relative_work_address,
            'relative_work_phone' => $record->relative_work_phone,
        ]);

        $this->schema([
            Tabs::make()->tabs([
                Tab::make(__('admin.student.student_information'))
                    ->schema([
                        Section::make()->columns(2)->schema([
                            TextInput::make('student_name')
                                ->label(__('admin.student.name'))
                                ->required(),
                            Select::make('student_gender')
                                ->label(__('admin.student.gender'))
                                ->options(Gender::class)
                                ->required(),
                            DatePicker::make('student_birth_date')
                                ->label(__('admin.student.birth_date'))
                                ->required(),
                            TextInput::make('student_civil_number')
                                ->label(__('admin.student.civil_number'))
                                ->required(),
                            TextInput::make('student_state')
                                ->label(__('admin.student.state'))
                                ->required(),
                            TextInput::make('student_governorate')
                                ->label(__('admin.student.governorate'))
                                ->required(),
                            TextInput::make('student_village')
                                ->label(__('admin.student.village'))
                                ->required(),
                            TextInput::make('student_house_number')
                                ->label(__('admin.student.house_number'))
                                ->required(),
                            TextInput::make('student_parents_social_status')
                                ->label(__('admin.student.parents_social_status'))
                                ->columnSpanFull()
                                ->required(),
                        ]),
                    ]),

                Tab::make(__('admin.student.father_information'))
                    ->schema([
                        Section::make()->columns(2)->schema([
                            TextInput::make('father_name')
                                ->label(__('admin.student.father_name'))
                                ->required(),
                            TextInput::make('father_phone')
                                ->label(__('admin.student.father_phone'))
                                ->required(),
                            TextInput::make('father_email')
                                ->label(__('admin.student.father_email'))
                                ->email(),
                            TextInput::make('father_id_number')
                                ->label(__('admin.student.father_national_id'))
                                ->required(),
                            TextInput::make('father_occupation')
                                ->label(__('admin.student.father_occupation')),
                            TextInput::make('father_work_address')
                                ->label(__('admin.student.father_work_address')),
                            TextInput::make('father_work_phone')
                                ->label(__('admin.student.father_work_phone')),
                            Toggle::make('father_is_guardian')
                                ->label(__('admin.student.father_is_guardian'))
                                ->live()
                                ->columnSpanFull(),
                        ]),
                    ]),

                Tab::make(__('admin.student.mother_information'))
                    ->schema([
                        Section::make()->columns(2)->schema([
                            TextInput::make('mother_name')
                                ->label(__('admin.student.mother_name'))
                                ->required(),
                            TextInput::make('mother_phone')
                                ->label(__('admin.student.mother_phone'))
                                ->required(),
                            TextInput::make('mother_email')
                                ->label(__('admin.student.mother_email'))
                                ->email(),
                            TextInput::make('mother_id_number')
                                ->label(__('admin.student.mother_national_id'))
                                ->required(),
                            TextInput::make('mother_occupation')
                                ->label(__('admin.student.mother_occupation')),
                            TextInput::make('mother_work_address')
                                ->label(__('admin.student.mother_work_address')),
                            TextInput::make('mother_work_phone')
                                ->label(__('admin.student.mother_work_phone')),
                            Toggle::make('mother_is_guardian')
                                ->label(__('admin.student.mother_is_guardian'))
                                ->live()
                                ->columnSpanFull(),
                        ]),
                    ]),

                Tab::make(__('admin.student.relative_information'))
                    ->schema([
                        Section::make()->columns(2)->schema([
                            TextInput::make('relative_name')
                                ->label(__('admin.student.relative_name'))
                                ->required(),
                            TextInput::make('relative_phone')
                                ->label(__('admin.student.relative_phone'))
                                ->required(),
                            TextInput::make('relative_email')
                                ->label(__('admin.student.relative_email'))
                                ->email(),
                            TextInput::make('relative_id_number')
                                ->label(__('admin.student.relative_national_id')),
                            TextInput::make('relative_occupation')
                                ->label(__('admin.student.relative_work')),
                            TextInput::make('relative_work_address')
                                ->label(__('admin.student.relative_work_address')),
                            TextInput::make('relative_work_phone')
                                ->label(__('admin.student.relative_work_phone')),
                        ]),
                    ]),
            ])->columnSpanFull(),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof PendingRegistration ?? false
        );

        $this->action(function (Application $record, array $data) {
            try {
                // First, persist any data the admin filled/corrected in the modal.
                $dto = UpdateApplicationDataDTO::fromValidated($data);
                app(UpdateApplicationDataAction::class)->execute($record, $dto);
                $record->refresh();

                // Then run validation + state transition.
                app(SubmitApplicationForReviewAction::class)->execute($record, Auth::id());

                Notification::make()
                    ->title(__('admin.application.actions.submit_for_review_success'))
                    ->success()
                    ->send();
            } catch (ValidationException $e) {
                Notification::make()
                    ->title(__('admin.application.actions.submit_for_review_failed'))
                    ->body(implode(' | ', array_merge(...array_values($e->errors()))))
                    ->danger()
                    ->persistent()
                    ->send();
            }
        });
    }
}
