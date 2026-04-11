<?php

namespace App\Actions\Season;

use App\Enums\ProgramType;
use App\Models\Season;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class EnsureSeasonCanBeActivated
{
    /**
     * Ensure a season can be activated under the active season constraints.
     *
     * Rules:
     * - A permanently closed season cannot be re-opened.
     * - Only one active season is allowed per type (Academic / Summer).
     * - At most two seasons can be active simultaneously (one of each type).
     */
    public function ensure(Season $season): void
    {
        if ($season->is_closed) {
            throw ValidationException::withMessages([
                'is_active' => __('admin.season.validation.cannot_reopen_closed'),
            ]);
        }

        $type = $season->type instanceof ProgramType
            ? $season->type
            : ProgramType::tryFrom((string) $season->type)
                ?? throw ValidationException::withMessages([
                    'type' => __('admin.season.validation.type_required_to_activate'),
                ]);

        $activeSeasons = Season::query()
            ->active()
            ->when(
                $season->exists,
                fn (Builder $query) => $query->whereKeyNot($season->getKey()),
            );

        if ((clone $activeSeasons)->where('type', $type->value)->exists()) {
            throw ValidationException::withMessages([
                'type' => __('admin.season.validation.one_active_per_type'),
            ]);
        }

        if ((clone $activeSeasons)->count() >= 2) {
            throw ValidationException::withMessages([
                'is_active' => __('admin.season.validation.max_active_seasons'),
            ]);
        }
    }
}
