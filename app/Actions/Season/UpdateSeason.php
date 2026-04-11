<?php

namespace App\Actions\Season;

use App\Models\Season;
use App\Rules\Season\SeasonRules;
use Illuminate\Support\Facades\Validator;

class UpdateSeason
{
    /**
     * Validate and update an existing season.
     *
     * `is_active` is managed exclusively via OpenSeason / CloseSeason and is
     * intentionally not accepted as user input here.
     *
     * @param  array{name: mixed, type: mixed, start_date?: mixed, end_date?: mixed, is_registration_open?: mixed}  $input
     */
    public function update(Season $season, array $input): Season
    {
        $validated = Validator::make($input, SeasonRules::rules())->validate();

        $season->fill([
            ...$validated,
            // is_registration_open may be absent when the toggle is not rendered
            'is_registration_open' => (bool) ($validated['is_registration_open'] ?? $season->is_registration_open),
        ]);

        if ($season->is_active) {
            app(EnsureSeasonCanBeActivated::class)->ensure($season);
        }

        $season->saveOrFail();

        return $season->refresh();
    }
}
