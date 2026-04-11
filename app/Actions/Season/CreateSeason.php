<?php

namespace App\Actions\Season;

use App\Enums\ProgramType;
use App\Models\Season;
use App\Rules\Season\SeasonRules;
use Illuminate\Support\Facades\Validator;

class CreateSeason
{
    /**
     * Validate and create a new season.
     *
     * The season will be activated automatically if no active season of the
     * same type exists. Otherwise it is created as inactive.
     *
     * @param  array{name: mixed, type: mixed, start_date?: mixed, end_date?: mixed}  $input
     */
    public function execute(array $input): Season
    {
        $validated = Validator::make($input, SeasonRules::rules())->validate();

        $isActive = ! Season::query()->active()->where('type', $validated['type'])->exists();

        $season = new Season([
            ...$validated,
            'is_active' => $isActive,
        ]);

        $season->saveOrFail();

        return $season;
    }
}
