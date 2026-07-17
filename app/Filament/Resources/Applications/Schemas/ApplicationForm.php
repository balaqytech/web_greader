<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

class ApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make(__('admin.application.enrollment_info'))
                        ->icon('heroicon-o-academic-cap')
                        ->description(__('admin.application.wizard_enrollment_description'))
                        ->schema([
                            TextInput::make('student_name')
                                ->label(__('admin.application.student_name'))
                                ->required(),
                            Select::make('student_gender')
                                ->label(__('admin.application.student_gender'))
                                ->options(Gender::class)
                                ->required(),
                            DatePicker::make('student_birth_date')
                                ->label(__('admin.application.student_birth_date'))
                                ->required(),
                            TextInput::make('student_civil_number')
                                ->label(__('admin.application.student_civil_number'))
                                ->required(),
                            TextInput::make('student_state')
                                ->label(__('admin.application.student_state'))
                                ->required(),
                            TextInput::make('student_governorate')
                                ->label(__('admin.application.student_governorate'))
                                ->required(),
                            TextInput::make('student_village')
                                ->label(__('admin.application.student_village'))
                                ->required(),
                            TextInput::make('student_house_number')
                                ->label(__('admin.application.student_house_number'))
                                ->required(),
                            TextInput::make('student_parents_social_status')
                                ->label(__('admin.application.student_parents_social_status'))
                                ->required(),
                            Select::make('student_relationship_with_guardian')
                                ->label(__('admin.application.student_relationship_with_guardian'))
                                ->options(GuardianRelationship::class)
                                ->required(),
                            Toggle::make('is_transfer_student')
                                ->label(__('admin.application.is_transfer_student'))
                                ->helperText(__('admin.application.is_transfer_student_help'))
                                ->default(false),
                        ])
                        ->columns(2),

                    Step::make(__('admin.application.father_data'))
                        ->icon('heroicon-o-user')
                        ->description(__('admin.application.wizard_father_description'))
                        ->schema([
                            TextInput::make('father_name')
                                ->label(__('admin.application_contacts.name'))
                                ->required(),
                            TextInput::make('father_phone')
                                ->label(__('admin.application_contacts.phone'))
                                ->tel()
                                ->required(),
                            TextInput::make('father_email')
                                ->label(__('admin.application_contacts.email'))
                                ->email(),
                            TextInput::make('father_id_number')
                                ->label(__('admin.application_contacts.id_number'))
                                ->required(),
                            TextInput::make('father_occupation')
                                ->label(__('admin.application_contacts.occupation'))
                                ->required(),
                            TextInput::make('father_work_address')
                                ->label(__('admin.application_contacts.work_address')),
                            TextInput::make('father_work_phone')
                                ->label(__('admin.application_contacts.work_phone')),
                            Toggle::make('father_is_guardian')
                                ->label(__('admin.application_contacts.is_guardian'))
                                ->live(),
                        ])
                        ->columns(2),

                    Step::make(__('admin.application.mother_data'))
                        ->icon('heroicon-o-user')
                        ->description(__('admin.application.wizard_mother_description'))
                        ->schema([
                            TextInput::make('mother_name')
                                ->label(__('admin.application_contacts.name'))
                                ->required(),
                            TextInput::make('mother_phone')
                                ->label(__('admin.application_contacts.phone'))
                                ->tel()
                                ->required(),
                            TextInput::make('mother_email')
                                ->label(__('admin.application_contacts.email'))
                                ->email(),
                            TextInput::make('mother_id_number')
                                ->label(__('admin.application_contacts.id_number'))
                                ->required(),
                            TextInput::make('mother_occupation')
                                ->label(__('admin.application_contacts.occupation'))
                                ->required(),
                            TextInput::make('mother_work_address')
                                ->label(__('admin.application_contacts.work_address')),
                            TextInput::make('mother_work_phone')
                                ->label(__('admin.application_contacts.work_phone')),
                            Toggle::make('mother_is_guardian')
                                ->label(__('admin.application_contacts.is_guardian'))
                                ->live()
                                ->state(fn (Get $get) => ! $get('father_is_guardian')),
                        ])
                        ->columns(2),

                    Step::make(__('admin.application.relative_data'))
                        ->icon('heroicon-o-user-group')
                        ->description(__('admin.application.wizard_emergency_contacts_description'))
                        ->schema([
                            TextInput::make('relative_name')
                                ->label(__('admin.application_contacts.name'))
                                ->required(),
                            TextInput::make('relative_phone')
                                ->label(__('admin.application_contacts.phone'))
                                ->tel()
                                ->required(),
                            TextInput::make('relative_email')
                                ->label(__('admin.application_contacts.email'))
                                ->email(),
                            TextInput::make('relative_id_number')
                                ->label(__('admin.application_contacts.id_number'))
                                ->required(),
                            TextInput::make('relative_occupation')
                                ->label(__('admin.application_contacts.occupation'))
                                ->required(),
                            TextInput::make('relative_work_address')
                                ->label(__('admin.application_contacts.work_address')),
                            TextInput::make('relative_work_phone')
                                ->label(__('admin.application_contacts.work_phone')),
                        ])
                        ->columns(1),
                ])
                    ->columnSpanFull(),
            ]);
    }
}
