<?php

namespace App\Actions\Season;

use App\Models\Season;

class CloseSeason
{
    /**
     * Permanently close the given season.
     *
     * A closed season cannot be re-opened.
     */
    public function execute(Season $season): Season
    {
        $season->forceFill([
            'is_registration_open' => false,
            'is_active' => false,
            'closed_at' => now(),
        ])->saveOrFail();

        return $season->refresh();
    }
}
