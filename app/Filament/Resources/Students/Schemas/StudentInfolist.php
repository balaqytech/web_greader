<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
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
                        TextEntry::make('relationship_with_guardian')
                            ->label(__('admin.student.relationship_with_guardian'))
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label(__('admin.student.created_at'))
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
