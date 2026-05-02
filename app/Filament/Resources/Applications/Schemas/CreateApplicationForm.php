<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Enums\ContactType;
use App\Enums\Gender;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Schemas\Schema;

class CreateApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Wizard\Step::make(__('admin.application.enrollment_info'))
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
                        ->columns(3),

                    Wizard\Step::make(__('admin.student.student_information'))
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
                        ->columns(2),

                    Wizard\Step::make(__('admin.application.contacts_section'))
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
                        ]),
                ])
                    ->columnSpanFull(),
            ]);
    }
}
