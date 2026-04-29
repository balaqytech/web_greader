<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\Gender;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make(__('admin.student.student_information'))
                        ->schema([
                            Select::make('branch_id')
                                ->label(__('admin.student.branch'))
                                ->relationship('branch', 'name')
                                ->required(),
                            TextInput::make('name')
                                ->label(__('admin.student.name'))
                                ->required(),
                            Select::make('gender')
                                ->label(__('admin.student.gender'))
                                ->options(Gender::class)
                                ->required(),
                            DatePicker::make('birth_date')
                                ->label(__('admin.student.birth_date'))
                                ->required(),
                            TextInput::make('civil_number')
                                ->label(__('admin.student.civil_number'))
                                ->required()
                                ->unique(ignoreRecord: true),
                            TextInput::make('state')
                                ->label(__('admin.student.state'))
                                ->required(),
                            TextInput::make('governorate')
                                ->label(__('admin.student.governorate'))
                                ->required(),
                            TextInput::make('village')
                                ->label(__('admin.student.village'))
                                ->required(),
                            TextInput::make('house_number')
                                ->label(__('admin.student.house_number'))
                                ->required(),
                            TextInput::make('parents_social_status')
                                ->label(__('admin.student.parents_social_status'))
                                ->required(),
                        ])
                        ->columns(2),

                    Step::make(__('admin.student.father_information'))
                        ->schema([
                            TextInput::make('father_name')
                                ->label(__('admin.student.father_name'))
                                ->required(),
                            TextInput::make('father_phone')
                                ->label(__('admin.student.father_phone'))
                                ->required(),
                            TextInput::make('father_email')
                                ->label(__('admin.student.father_email'))
                                ->required(),
                            TextInput::make('father_national_id')
                                ->label(__('admin.student.father_national_id'))
                                ->required(),
                            TextInput::make('father_occupation')
                                ->label(__('admin.student.father_occupation'))
                                ->required(),
                            TextInput::make('father_work_address')
                                ->label(__('admin.student.father_work_address')),
                            TextInput::make('father_work_phone')
                                ->label(__('admin.student.father_work_phone')),
                            Toggle::make('father_is_guardian')
                                ->label(__('admin.student.father_is_guardian'))
                                ->live()
                                ->default(false),
                        ])
                        ->columns(2),

                    Step::make(__('admin.student.mother_information'))
                        ->schema([
                            TextInput::make('mother_name')
                                ->label(__('admin.student.mother_name'))
                                ->required(),
                            TextInput::make('mother_phone')
                                ->label(__('admin.student.mother_phone'))
                                ->required(),
                            TextInput::make('mother_email')
                                ->label(__('admin.student.mother_email'))
                                ->required(),
                            TextInput::make('mother_national_id')
                                ->label(__('admin.student.mother_national_id'))
                                ->required(),
                            TextInput::make('mother_occupation')
                                ->label(__('admin.student.mother_occupation'))
                                ->required(),
                            TextInput::make('mother_work_address')
                                ->label(__('admin.student.mother_work_address')),
                            TextInput::make('mother_work_phone')
                                ->label(__('admin.student.mother_work_phone')),
                            Toggle::make('mother_is_guardian')
                                ->label(__('admin.student.mother_is_guardian'))
                                ->live()
                                ->default(false),
                        ])
                        ->columns(2),

                    Step::make(__('admin.student.relative_information'))
                        ->schema([
                            TextInput::make('relative_name')
                                ->label(__('admin.student.relative_name')),
                            TextInput::make('relative_phone')
                                ->label(__('admin.student.relative_phone')),
                            TextInput::make('relative_email')
                                ->label(__('admin.student.relative_email')),
                            TextInput::make('relative_relationship')
                                ->label(__('admin.student.relative_relationship')),
                            TextInput::make('relative_address')
                                ->label(__('admin.student.relative_address')),
                            TextInput::make('relative_work')
                                ->label(__('admin.student.relative_work')),
                            TextInput::make('relative_work_phone')
                                ->label(__('admin.student.relative_work_phone')),
                            TextInput::make('relative_work_address')
                                ->label(__('admin.student.relative_work_address')),
                        ])
                        ->columns(2),
                ])->columnSpanFull(),
            ]);
    }
}
