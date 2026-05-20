<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AppliesDashboardFilters;
use App\Models\Program;
use App\Models\Season;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class DetailedRegistrationsChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use AppliesDashboardFilters;

    protected static ?int $sort = 2;

    protected ?string $heading = 'توزيع المسجلين حسب البرامج';

    protected function getData(): array
    {
        $activeSeasonIds = Season::where('is_active', true)->pluck('id')->toArray();
        $programs = Program::where('is_active', true)->orderBy('sort_order')->get();

        $colors = [
            '#ef4444', '#f97316', '#f59e0b', '#eab308',
            '#84cc16', '#22c55e', '#10b981', '#14b8a6',
            '#06b6d4', '#0ea5e9', '#3b82f6', '#6366f1',
        ];

        $labels = [];
        $data = [];
        $backgroundColors = [];

        foreach ($programs as $index => $program) {
            $count = $this->applyFilters(
                DB::table('leads')->where('program_id', $program->id),
                $activeSeasonIds
            )->count();

            $labels[] = $program->name . ': ' . $count;
            $data[] = $count;
            $backgroundColors[] = $colors[$index % count($colors)];
        }

        return [
            'datasets' => [
                [
                    'label' => 'المسجلين',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
