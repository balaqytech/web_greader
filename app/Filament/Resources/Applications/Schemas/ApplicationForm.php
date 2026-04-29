<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Enums\Gender;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.student.student_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
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

                Section::make(__('admin.student.father_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
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
                            ->label(__('admin.student.father_occupation'))
                            ->required(),
                        TextInput::make('father_work_address')
                            ->label(__('admin.student.father_work_address'))
                            ->required(),
                        TextInput::make('father_work_phone')
                            ->label(__('admin.student.father_work_phone'))
                            ->required(),
                        Toggle::make('father_is_guardian')
                            ->label(__('admin.student.father_is_guardian'))
                            ->live()
                            ->default(true)
                            ->required(),
                    ]),

                Section::make(__('admin.student.mother_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
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
                            ->default(false),
                    ]),

                Section::make(__('admin.student.relative_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
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
            ]);
    }
}
