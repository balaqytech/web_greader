<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

trait AppliesDashboardFilters
{
    protected function applyFilters(Builder $query, array $activeSeasonIds): Builder
    {
        if (!empty($this->filters['startDate'])) {
            $query->whereDate('leads.created_at', '>=', $this->filters['startDate']);
        }
        if (!empty($this->filters['endDate'])) {
            $query->whereDate('leads.created_at', '<=', $this->filters['endDate']);
        }
        if (!empty($this->filters['status'])) {
            $query->where('leads.status', $this->filters['status']);
        }
        if (!empty($this->filters['source'])) {
            $query->where('leads.source', $this->filters['source']);
        }
        if (!empty($this->filters['season_id'])) {
            $query->where('leads.season_id', $this->filters['season_id']);
        } else {
            $query->whereIn('leads.season_id', $activeSeasonIds);
        }
        if (!empty($this->filters['branch_id'])) {
            $query->where('leads.branch_id', $this->filters['branch_id']);
        }
        if (!empty($this->filters['program_type'])) {
            $query->where('leads.program_type', $this->filters['program_type']);
        }

        return $query;
    }

    protected function applyBranchFilterToOuterQuery(EloquentBuilder $query): EloquentBuilder
    {
        if (!empty($this->filters['branch_id'])) {
            $query->where('id', $this->filters['branch_id']);
        }
        return $query;
    }
}
