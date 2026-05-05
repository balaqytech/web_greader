<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
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
                        Select::make('relationship_with_guardian')
                            ->options(GuardianRelationship::class)
                            ->label(__('admin.student.relationship_with_guardian'))
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
