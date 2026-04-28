<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Models\Application;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.application.application_info'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('ref_no')
                            ->label(__('admin.application.ref_no')),
                        TextEntry::make('status')
                            ->label(__('admin.application.status'))
                            ->badge()
                            ->color(fn (Application $record) => $record->status->getColor())
                            ->formatStateUsing(fn (Application $record) => $record->status->getLabel()),
                        TextEntry::make('created_at')
                            ->label(__('admin.lead.created_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('branch.name')
                            ->label(__('admin.branch.label'))
                            ->placeholder('-'),
                        TextEntry::make('season.name')
                            ->label(__('admin.season.label'))
                            ->placeholder('-'),
                        TextEntry::make('program.name')
                            ->label(__('admin.program.name'))
                            ->placeholder('-'),
                        TextEntry::make('rejection_reason')
                            ->label(__('admin.application.rejection_reason'))
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->visible(fn (Application $record) => filled($record->rejection_reason)),
                    ]),

                Section::make(__('admin.student.student_information'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('student_name')
                            ->label(__('admin.student.name'))
                            ->placeholder('-'),
                        TextEntry::make('student_gender')
                            ->label(__('admin.student.gender'))
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('student_birth_date')
                            ->label(__('admin.student.birth_date'))
                            ->date()
                            ->placeholder('-'),
                        TextEntry::make('student_civil_number')
                            ->label(__('admin.student.civil_number'))
                            ->placeholder('-'),
                        TextEntry::make('student_state')
                            ->label(__('admin.student.state'))
                            ->placeholder('-'),
                        TextEntry::make('student_governorate')
                            ->label(__('admin.student.governorate'))
                            ->placeholder('-'),
                        TextEntry::make('student_village')
                            ->label(__('admin.student.village'))
                            ->placeholder('-'),
                        TextEntry::make('student_house_number')
                            ->label(__('admin.student.house_number'))
                            ->placeholder('-'),
                        TextEntry::make('student_parents_social_status')
                            ->label(__('admin.student.parents_social_status'))
                            ->placeholder('-'),
                    ]),

                Section::make(__('admin.student.father_information'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('father_name')
                            ->label(__('admin.student.father_name'))
                            ->placeholder('-'),
                        TextEntry::make('father_phone')
                            ->label(__('admin.student.father_phone'))
                            ->placeholder('-'),
                        TextEntry::make('father_email')
                            ->label(__('admin.student.father_email'))
                            ->placeholder('-'),
                        TextEntry::make('father_id_number')
                            ->label(__('admin.student.father_national_id'))
                            ->placeholder('-'),
                        TextEntry::make('father_occupation')
                            ->label(__('admin.student.father_occupation'))
                            ->placeholder('-'),
                        TextEntry::make('father_work_address')
                            ->label(__('admin.student.father_work_address'))
                            ->placeholder('-'),
                        TextEntry::make('father_work_phone')
                            ->label(__('admin.student.father_work_phone'))
                            ->placeholder('-'),
                        IconEntry::make('father_is_guardian')
                            ->label(__('admin.student.father_is_guardian'))
                            ->boolean(),
                    ]),

                Section::make(__('admin.student.mother_information'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('mother_name')
                            ->label(__('admin.student.mother_name'))
                            ->placeholder('-'),
                        TextEntry::make('mother_phone')
                            ->label(__('admin.student.mother_phone'))
                            ->placeholder('-'),
                        TextEntry::make('mother_email')
                            ->label(__('admin.student.mother_email'))
                            ->placeholder('-'),
                        TextEntry::make('mother_id_number')
                            ->label(__('admin.student.mother_national_id'))
                            ->placeholder('-'),
                        TextEntry::make('mother_occupation')
                            ->label(__('admin.student.mother_occupation'))
                            ->placeholder('-'),
                        TextEntry::make('mother_work_address')
                            ->label(__('admin.student.mother_work_address'))
                            ->placeholder('-'),
                        TextEntry::make('mother_work_phone')
                            ->label(__('admin.student.mother_work_phone'))
                            ->placeholder('-'),
                        IconEntry::make('mother_is_guardian')
                            ->label(__('admin.student.mother_is_guardian'))
                            ->boolean(),
                    ]),

                Section::make(__('admin.student.relative_information'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('relative_name')
                            ->label(__('admin.student.relative_name'))
                            ->placeholder('-'),
                        TextEntry::make('relative_phone')
                            ->label(__('admin.student.relative_phone'))
                            ->placeholder('-'),
                        TextEntry::make('relative_email')
                            ->label(__('admin.student.relative_email'))
                            ->placeholder('-'),
                        TextEntry::make('relative_id_number')
                            ->label(__('admin.student.relative_national_id'))
                            ->placeholder('-'),
                        TextEntry::make('relative_occupation')
                            ->label(__('admin.student.relative_work'))
                            ->placeholder('-'),
                        TextEntry::make('relative_work_address')
                            ->label(__('admin.student.relative_work_address'))
                            ->placeholder('-'),
                        TextEntry::make('relative_work_phone')
                            ->label(__('admin.student.relative_work_phone'))
                            ->placeholder('-'),
                    ]),

                Section::make(__('admin.application.activity'))
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('activities')
                            ->label('')
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('from_state')
                                        ->label(__('admin.application.activity_from'))
                                        ->formatStateUsing(fn (string $state) => __("admin.application.states.{$state}"))
                                        ->badge(),
                                    TextEntry::make('to_state')
                                        ->label(__('admin.application.activity_to'))
                                        ->formatStateUsing(fn (string $state) => __("admin.application.states.{$state}"))
                                        ->badge(),
                                    TextEntry::make('transitionedBy.name')
                                        ->label(__('admin.application.activity_by'))
                                        ->placeholder(__('admin.application.activity_system')),
                                    TextEntry::make('transitioned_at')
                                        ->label(__('admin.application.activity_at'))
                                        ->dateTime(),
                                    TextEntry::make('notes')
                                        ->label(__('admin.application.notes'))
                                        ->placeholder('-')
                                        ->columnSpanFull(),
                                ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
