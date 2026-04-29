<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Enums\Gender;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CreateApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.application.enrollment_info'))
                    ->columnSpanFull()
                    ->columns(3)
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
                    ]),

                Section::make(__('admin.student.student_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('student_name')
                            ->label(__('admin.student.name'))
                            ->required(),
                        Select::make('student_gender')
                            ->label(__('admin.student.gender'))
                            ->options(Gender::class),
                        DatePicker::make('student_birth_date')
                            ->label(__('admin.student.birth_date')),
                        TextInput::make('student_civil_number')
                            ->label(__('admin.student.civil_number')),
                        TextInput::make('student_state')
                            ->label(__('admin.student.state')),
                        TextInput::make('student_governorate')
                            ->label(__('admin.student.governorate')),
                        TextInput::make('student_village')
                            ->label(__('admin.student.village')),
                        TextInput::make('student_house_number')
                            ->label(__('admin.student.house_number')),
                        TextInput::make('student_parents_social_status')
                            ->label(__('admin.student.parents_social_status'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('admin.student.father_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('father_name')
                            ->label(__('admin.student.father_name')),
                        TextInput::make('father_phone')
                            ->label(__('admin.student.father_phone')),
                        TextInput::make('father_email')
                            ->label(__('admin.student.father_email'))
                            ->email(),
                        TextInput::make('father_id_number')
                            ->label(__('admin.student.father_national_id')),
                        TextInput::make('father_occupation')
                            ->label(__('admin.student.father_occupation')),
                        TextInput::make('father_work_address')
                            ->label(__('admin.student.father_work_address')),
                        TextInput::make('father_work_phone')
                            ->label(__('admin.student.father_work_phone')),
                        Toggle::make('father_is_guardian')
                            ->label(__('admin.student.father_is_guardian'))
                            ->live()
                            ->default(false),
                    ]),

                Section::make(__('admin.student.mother_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('mother_name')
                            ->label(__('admin.student.mother_name')),
                        TextInput::make('mother_phone')
                            ->label(__('admin.student.mother_phone')),
                        TextInput::make('mother_email')
                            ->label(__('admin.student.mother_email'))
                            ->email(),
                        TextInput::make('mother_id_number')
                            ->label(__('admin.student.mother_national_id')),
                        TextInput::make('mother_occupation')
                            ->label(__('admin.student.mother_occupation')),
                        TextInput::make('mother_work_address')
                            ->label(__('admin.student.mother_work_address')),
                        TextInput::make('mother_work_phone')
                            ->label(__('admin.student.mother_work_phone')),
                        Toggle::make('mother_is_guardian')
                            ->label(__('admin.student.mother_is_guardian'))
                            ->live()
                            ->default(false),
                    ]),

                Section::make(__('admin.student.relative_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('relative_name')
                            ->label(__('admin.student.relative_name')),
                        TextInput::make('relative_phone')
                            ->label(__('admin.student.relative_phone')),
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
            ]);
    }
}
