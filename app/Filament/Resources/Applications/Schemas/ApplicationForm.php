<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                            Select::make('branch_id')
                                ->label(__('admin.branch.label'))
                                ->relationship('branch', 'name')
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
                        ->columns(2),

                    Step::make(__('admin.student.student_information'))
                        ->icon('heroicon-o-user')
                        ->description(__('admin.application.wizard_student_description'))
                        ->schema([
                            TextInput::make('applicationStudent.name')
                                ->label(__('admin.student.name'))
                                ->required(),
                            Select::make('applicationStudent.gender')
                                ->label(__('admin.student.gender'))
                                ->options(Gender::class)
                                ->required(),
                            DatePicker::make('applicationStudent.birth_date')
                                ->label(__('admin.student.birth_date'))
                                ->required(),
                            TextInput::make('applicationStudent.civil_number')
                                ->label(__('admin.student.civil_number'))
                                ->required(),
                            TextInput::make('applicationStudent.state')
                                ->label(__('admin.student.state'))
                                ->required(),
                            TextInput::make('applicationStudent.governorate')
                                ->label(__('admin.student.governorate'))
                                ->required(),
                            TextInput::make('applicationStudent.village')
                                ->label(__('admin.student.village'))
                                ->required(),
                            TextInput::make('applicationStudent.house_number')
                                ->label(__('admin.student.house_number'))
                                ->required(),
                            TextInput::make('applicationStudent.parents_social_status')
                                ->label(__('admin.student.parents_social_status'))
                                ->required(),
                            Select::make('applicationStudent.relationship_with_guardian')
                                ->label(__('admin.student.relationship_with_guardian'))
                                ->options(GuardianRelationship::class)
                                ->required(),
                        ])
                        ->columns(2),

                    Step::make(__('admin.application.contacts_section'))
                        ->icon('heroicon-o-users')
                        ->description(__('admin.application.wizard_contacts_description'))
                        ->schema([
                            Repeater::make('contacts')
                                ->schema([
                                    Select::make('relationship')
                                        ->label(__('admin.application_contacts.relationship'))
                                        ->options(GuardianRelationship::class)
                                        ->required(),
                                    TextInput::make('name')
                                        ->label(__('admin.application_contacts.name'))
                                        ->required(),
                                    TextInput::make('phone')
                                        ->label(__('admin.application_contacts.phone'))
                                        ->tel()
                                        ->required(),
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
                        ]),
                ])
                    ->columnSpanFull(),
            ]);
    }
}
