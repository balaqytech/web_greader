<?php

namespace App\Actions\Season;

use App\Models\Season;
use App\Rules\Season\SeasonRules;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Validator;

class UpdateSeason
{
    public function __construct(
        private EnsureSeasonCanBeActivated $ensureSeasonCanBeActivated
    ) {}
    /**
     * Validate and update an existing season.
     *
     * `is_active` is managed exclusively via OpenSeason / CloseSeason and is
     * intentionally not accepted as user input here.
     *
     * @param  array{name: mixed, type: mixed, start_date?: mixed, end_date?: mixed}  $input
     */
    public function execute(Season $season, array $input): Season
    {
        $validated = Validator::make($input, SeasonRules::rules())->validate();

        $season->fill([
            ...$validated,
        ]);

        try {
            if ($season->is_active) {
                $this->ensureSeasonCanBeActivated->ensure($season);
            }

            $season->saveOrFail();
        } catch (\Exception $th) {
            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();
        }

        return $season->refresh();
    }
}
