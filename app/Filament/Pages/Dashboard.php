<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Season;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('startDate')
                    ->label('من تاريخ'),
                DatePicker::make('endDate')
                    ->label('إلى تاريخ'),
                Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'new' => 'جديد',
                        'contacted' => 'تم التواصل',
                        'not_interested' => 'غير مهتم',
                    ]),
                Select::make('source')
                    ->label('المصدر')
                    ->options([
                        'website' => 'الموقع الإلكتروني',
                        'whatsapp_bot' => 'بوت الواتساب',
                    ]),
                Select::make('season_id')
                    ->label('الموسم')
                    ->options(Season::pluck('name', 'id')),
                Select::make('branch_id')
                    ->label('الفرع')
                    ->options(Branch::pluck('name', 'id')),
                Select::make('program_type')
                    ->label('نوع البرنامج')
                    ->options([
                        'academic' => 'دراسي',
                        'summer' => 'صيفي',
                    ]),
            ]);
    }
}
