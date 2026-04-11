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
    public function close(Season $season): Season
    {
        $season->forceFill([
            'is_registration_open' => false,
            'is_active' => false,
            'is_closed' => true,
        ])->saveOrFail();

        return $season->refresh();
    }
}
