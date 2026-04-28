<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Student Details')
                    ->tabs([
                        Tabs\Tab::make(__('admin.student.student_information'))
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('name')
                                        ->label(__('admin.student.name')),
                                    TextEntry::make('guardian.name')
                                        ->label(__('admin.student.guardian_name')),
                                    TextEntry::make('branch.name')
                                        ->label(__('admin.student.branch_name')),
                                    TextEntry::make('gender')
                                        ->label(__('admin.student.gender'))
                                        ->badge(),
                                    TextEntry::make('birth_date')
                                        ->label(__('admin.student.birth_date'))
                                        ->date(),
                                    TextEntry::make('civil_number')
                                        ->label(__('admin.student.civil_number')),
                                    TextEntry::make('state')
                                        ->label(__('admin.student.state')),
                                    TextEntry::make('governorate')
                                        ->label(__('admin.student.governorate')),
                                    TextEntry::make('village')
                                        ->label(__('admin.student.village')),
                                    TextEntry::make('house_number')
                                        ->label(__('admin.student.house_number')),
                                    TextEntry::make('parents_social_status')
                                        ->label(__('admin.student.parents_social_status')),
                                    TextEntry::make('created_at')
                                        ->label(__('admin.student.created_at'))
                                        ->dateTime()
                                        ->placeholder('-'),
                                ]),
                            ]),
                        Tabs\Tab::make(__('admin.student.father_information'))
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('father_data.name')->label(__('admin.student.father_name'))->placeholder('-'),
                                    TextEntry::make('father_data.phone')->label(__('admin.student.father_phone'))->placeholder('-'),
                                    TextEntry::make('father_data.email')->label(__('admin.student.father_email'))->placeholder('-'),
                                    TextEntry::make('father_data.national_id')->label(__('admin.student.father_national_id'))->placeholder('-'),
                                    TextEntry::make('father_data.occupation')->label(__('admin.student.father_occupation'))->placeholder('-'),
                                    TextEntry::make('father_data.work_address')->label(__('admin.student.father_work_address'))->placeholder('-'),
                                    TextEntry::make('father_data.work_phone')->label(__('admin.student.father_work_phone'))->placeholder('-'),
                                ]),
                            ]),
                        Tabs\Tab::make(__('admin.student.mother_information'))
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('mother_data.name')->label(__('admin.student.mother_name'))->placeholder('-'),
                                    TextEntry::make('mother_data.phone')->label(__('admin.student.mother_phone'))->placeholder('-'),
                                    TextEntry::make('mother_data.email')->label(__('admin.student.mother_email'))->placeholder('-'),
                                    TextEntry::make('mother_data.national_id')->label(__('admin.student.mother_national_id'))->placeholder('-'),
                                    TextEntry::make('mother_data.occupation')->label(__('admin.student.mother_occupation'))->placeholder('-'),
                                    TextEntry::make('mother_data.work_address')->label(__('admin.student.mother_work_address'))->placeholder('-'),
                                    TextEntry::make('mother_data.work_phone')->label(__('admin.student.mother_work_phone'))->placeholder('-'),
                                ]),
                            ]),
                        Tabs\Tab::make(__('admin.student.relative_information'))
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('relative_data.name')->label(__('admin.student.relative_name'))->placeholder('-'),
                                    TextEntry::make('relative_data.phone')->label(__('admin.student.relative_phone'))->placeholder('-'),
                                    TextEntry::make('relative_data.email')->label(__('admin.student.relative_email'))->placeholder('-'),
                                    TextEntry::make('relative_data.address')->label(__('admin.student.relative_address'))->placeholder('-'),
                                    TextEntry::make('relative_data.work')->label(__('admin.student.relative_work'))->placeholder('-'),
                                    TextEntry::make('relative_data.work_address')->label(__('admin.student.relative_work_address'))->placeholder('-'),
                                    TextEntry::make('relative_data.work_phone')->label(__('admin.student.relative_work_phone'))->placeholder('-'),

                                ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
