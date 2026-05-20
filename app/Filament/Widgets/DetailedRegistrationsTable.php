<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AppliesDashboardFilters;
use App\Models\Branch;
use App\Models\Program;
use App\Models\Season;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class DetailedRegistrationsTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use AppliesDashboardFilters;

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return 'تفاصيل المسجلين حسب البرنامج / الصف';
    }

    public function table(Table $table): Table
    {
        $activeSeasonIds = Season::where('is_active', true)->pluck('id')->toArray();
        $programs = Program::where('is_active', true)->orderBy('sort_order')->get();

        $query = Branch::query()->select('branches.*');
        $query = $this->applyBranchFilterToOuterQuery($query);

        foreach ($programs as $program) {
            $query->selectSub(
                $this->applyFilters(
                    DB::table('leads')
                        ->whereColumn('leads.branch_id', 'branches.id')
                        ->where('leads.program_id', $program->id),
                    $activeSeasonIds
                )->selectRaw('count(*)'),
                "program_{$program->id}_count"
            );
        }

        $query->selectSub(
            $this->applyFilters(
                DB::table('leads')
                    ->whereColumn('leads.branch_id', 'branches.id'),
                $activeSeasonIds
            )->selectRaw('count(*)'),
            'total_applications_count'
        );

        $columns = [
            TextColumn::make('index')
                ->label('م')
                ->rowIndex(),
            TextColumn::make('name')
                ->label('الفرع')
                ->searchable(),
        ];

        foreach ($programs as $program) {
            $columns[] = TextColumn::make("program_{$program->id}_count")
                ->label($program->name)
                ->summarize(Sum::make()->label('الإجمالي'));
        }

        $columns[] = TextColumn::make('total_applications_count')
            ->label('الإجمالي الكلي')
            ->summarize(Sum::make()->label('الإجمالي'));

        return $table
            ->query($query)
            ->columns($columns)
            ->paginated(false);
    }
}
