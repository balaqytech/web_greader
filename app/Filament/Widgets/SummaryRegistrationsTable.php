<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AppliesDashboardFilters;
use App\Models\Branch;
use App\Models\Season;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class SummaryRegistrationsTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use AppliesDashboardFilters;

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return 'ملخص المسجلين في مدرسة القارئ العبقري';
    }

    public function table(Table $table): Table
    {
        $activeSeasonIds = Season::where('is_active', true)->pluck('id')->toArray();

        $query = Branch::query()->select('branches.*');
        $query = $this->applyBranchFilterToOuterQuery($query);

        return $table
            ->query(
                $query
                    ->selectSub(
                        $this->applyFilters(
                            DB::table('leads')
                                ->whereColumn('leads.branch_id', 'branches.id')
                                ->where('leads.program_type', 'academic'),
                            $activeSeasonIds
                        )->selectRaw('count(*)'),
                        'academic_count'
                    )
                    ->selectSub(
                        $this->applyFilters(
                            DB::table('leads')
                                ->whereColumn('leads.branch_id', 'branches.id')
                                ->where('leads.program_type', 'summer'),
                            $activeSeasonIds
                        )->selectRaw('count(*)'),
                        'summer_count'
                    )
                    ->selectSub(
                        $this->applyFilters(
                            DB::table('leads')
                                ->whereColumn('leads.branch_id', 'branches.id'),
                            $activeSeasonIds
                        )->selectRaw('count(*)'),
                        'total_applications_count'
                    )
            )
            ->columns([
                TextColumn::make('index')
                    ->label('م')
                    ->rowIndex(),
                TextColumn::make('name')
                    ->label('الفرع')
                    ->searchable(),
                TextColumn::make('academic_count')
                    ->label('برنامج رواء (دراسي)')
                    ->summarize(Sum::make()->label('الإجمالي')),
                TextColumn::make('summer_count')
                    ->label('برنامج سنا (صيفي)')
                    ->summarize(Sum::make()->label('الإجمالي')),
                TextColumn::make('total_applications_count')
                    ->label('الإجمالي')
                    ->summarize(Sum::make()->label('الإجمالي')),
            ])
            ->paginated(false);
    }
}
