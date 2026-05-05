<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Actions\Applications\PrefillFormFromExistingAction;
use App\Enums\ContactType;
use App\Enums\Gender;
use App\Models\Guardian;
use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

class CreateFromExistingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    self::guardianSelectionStep(),
                    self::enrollmentStep(),
                    self::studentStep(),
                    self::contactsStep(),
                ])
                    ->columnSpanFull(),
            ]);
    }

    private static function guardianSelectionStep(): Step
    {
        return Step::make(__('admin.application.wizard_select_guardian'))
            ->icon('heroicon-o-user-circle')
            ->description(__('admin.application.wizard_select_guardian_description'))
            ->schema([
                Select::make('guardian_id')
                    ->label(__('admin.guardian.label'))
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return Guardian::query()
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('id_number', 'like', "%{$search}%")
                            ->limit(20)
                            ->get()
                            ->mapWithKeys(fn (Guardian $g) => [
                                $g->id => "{$g->name} — {$g->phone}",
                            ])
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $guardian = Guardian::find($value);

                        return $guardian ? "{$guardian->name} — {$guardian->phone}" : null;
                    })
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        // Reset student selection and clear prefilled data when guardian changes
                        $set('student_id', null);
                        $set('applicationStudent', []);
                        $set('contacts', []);
                    }),

                Select::make('student_id')
                    ->label(__('admin.student.label'))
                    ->options(function (Get $get): array {
                        $guardianId = $get('guardian_id');
                        if (! $guardianId) {
                            return [];
                        }

                        $students = Student::where('guardian_id', $guardianId)
                            ->pluck('name', 'id')
                            ->toArray();

                        return ['new' => __('admin.application.new_student')] + $students;
                    })
                    ->visible(fn (Get $get): bool => filled($get('guardian_id')))
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                        $guardianId = $get('guardian_id');
                        if (! $guardianId) {
                            return;
                        }

                        if ($state === 'new' || $state === null) {
                            // New student — prefill contacts only from guardian's most recent sibling
                            $guardian = Guardian::find($guardianId);
                            if ($guardian) {
                                $data = PrefillFormFromExistingAction::fromGuardianOnly($guardian);
                                $set('contacts', $data['contacts']);
                                $set('applicationStudent', []);

                                Notification::make()
                                    ->title(__('admin.application.guardian_data_prefilled'))
                                    ->success()
                                    ->send();
                            }
                        } else {
                            // Existing student — prefill everything
                            $student = Student::with('contacts')->find($state);
                            if ($student) {
                                $data = PrefillFormFromExistingAction::fromStudent($student);
                                $set('applicationStudent', $data['applicationStudent']);
                                $set('contacts', $data['contacts']);

                                Notification::make()
                                    ->title(__('admin.application.student_data_prefilled'))
                                    ->success()
                                    ->send();
                            }
                        }
                    }),
            ])
            ->columns(2);
    }

    private static function enrollmentStep(): Step
    {
        return Step::make(__('admin.application.enrollment_info'))
            ->icon('heroicon-o-academic-cap')
            ->description(__('admin.application.wizard_enrollment_description'))
            ->schema([
                Select::make('branch_id')
                    ->label(__('admin.branch.label'))
                    ->relationship('branch', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('season_id')
                    ->label(__('admin.season.label'))
                    ->relationship('season', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('program_id')
                    ->label(__('admin.program.name'))
                    ->relationship('program', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
            ])
            ->columns(3);
    }

    private static function studentStep(): Step
    {
        return Step::make(__('admin.student.student_information'))
            ->icon('heroicon-o-user')
            ->description(__('admin.application.wizard_student_description'))
            ->schema([
                TextInput::make('applicationStudent.name')
                    ->label(__('admin.student.name'))
                    ->required(),
                Select::make('applicationStudent.gender')
                    ->label(__('admin.student.gender'))
                    ->options(Gender::class),
                DatePicker::make('applicationStudent.birth_date')
                    ->label(__('admin.student.birth_date')),
                TextInput::make('applicationStudent.civil_number')
                    ->label(__('admin.student.civil_number'))
                    ->required(),
                TextInput::make('applicationStudent.state')
                    ->label(__('admin.student.state')),
                TextInput::make('applicationStudent.governorate')
                    ->label(__('admin.student.governorate')),
                TextInput::make('applicationStudent.village')
                    ->label(__('admin.student.village')),
                TextInput::make('applicationStudent.house_number')
                    ->label(__('admin.student.house_number')),
                TextInput::make('applicationStudent.parents_social_status')
                    ->label(__('admin.student.parents_social_status'))
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    private static function contactsStep(): Step
    {
        return Step::make(__('admin.application.contacts_section'))
            ->icon('heroicon-o-users')
            ->description(__('admin.application.wizard_contacts_description'))
            ->schema([
                Repeater::make('contacts')
                    ->schema([
                        Select::make('type')
                            ->label(__('admin.application_contacts.type_label'))
                            ->options(ContactType::class)
                            ->required(),
                        TextInput::make('relationship')
                            ->label(__('admin.application_contacts.relationship'))
                            ->placeholder(__('admin.application_contacts.relationship_placeholder')),
                        TextInput::make('name')
                            ->label(__('admin.application_contacts.name'))
                            ->required(),
                        TextInput::make('phone')
                            ->label(__('admin.application_contacts.phone')),
                        TextInput::make('email')
                            ->label(__('admin.application_contacts.email'))
                            ->email(),
                        TextInput::make('id_number')
                            ->label(__('admin.application_contacts.id_number')),
                        TextInput::make('occupation')
                            ->label(__('admin.application_contacts.occupation')),
                        TextInput::make('work_address')
                            ->label(__('admin.application_contacts.work_address')),
                        TextInput::make('work_phone')
                            ->label(__('admin.application_contacts.work_phone')),
                        Toggle::make('is_guardian')
                            ->label(__('admin.application_contacts.is_guardian'))
                            ->live(),
                    ])
                    ->columns(2)
                    ->minItems(3)
                    ->defaultItems(3)
                    ->collapsible()
                    ->cloneable()
                    ->itemLabel(fn (array $state): ?string => ($state['name'] ?? __('admin.application_contacts.new_contact'))
                        .($state['is_guardian'] ?? false ? ' — '.__('admin.application_contacts.guardian') : '')),
            ]);
    }
}
