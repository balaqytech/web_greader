<?php

namespace App\Actions\Season;

use App\Models\Season;

class OpenSeason
{
    /**
     * Activate the given season.
     *
     * Permanently closed seasons cannot be re-opened.
     */
    public function open(Season $season): Season
    {
        app(EnsureSeasonCanBeActivated::class)->ensure($season);

        $season->forceFill([
            'is_active' => true,
        ])->saveOrFail();

        return $season->refresh();
    }
}
