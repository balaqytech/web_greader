<?php

namespace App\Actions\Season;

use App\Models\Season;
use Filament\Notifications\Notification;

class OpenSeason
{
    public function __construct(
        private EnsureSeasonCanBeActivated $ensureSeasonCanBeActivated
    ) {}
    /**
     * Activate the given season.
     *
     * Permanently closed seasons cannot be re-opened.
     */
    public function execute(Season $season): Season
    {
        try {
            $this->ensureSeasonCanBeActivated->ensure($season);

            $season->forceFill([
                'is_active' => true,
            ])->saveOrFail();
        } catch (\Exception $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();
        }

        return $season->refresh();
    }
}
