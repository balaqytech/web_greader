<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AppliesDashboardFilters;
use App\Models\Season;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class SummaryRegistrationsChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use AppliesDashboardFilters;

    protected static ?int $sort = 1;

    protected ?string $heading = 'نسبة التسجيل (رواء vs سنا)';

    protected function getData(): array
    {
        $activeSeasonIds = Season::where('is_active', true)->pluck('id')->toArray();

        $academicCount = $this->applyFilters(
            DB::table('leads')->where('program_type', 'academic'),
            $activeSeasonIds
        )->count();

        $summerCount = $this->applyFilters(
            DB::table('leads')->where('program_type', 'summer'),
            $activeSeasonIds
        )->count();

        return [
            'datasets' => [
                [
                    'label' => 'المسجلين',
                    'data' => [$academicCount, $summerCount],
                    'backgroundColor' => ['#ef4444', '#eab308'],
                ],
            ],
            'labels' => [
                'برنامج رواء (دراسي): ' . $academicCount,
                'برنامج سنا (صيفي): ' . $summerCount,
            ],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
