<?php

namespace App\Filament\Resources\Seasons\Pages;

use App\Actions\Season\CreateSeason;
use App\Filament\Resources\Seasons\SeasonResource;
use App\Models\Season;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSeasons extends ManageRecords
{
    protected static string $resource = SeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn(array $data): Season => app(CreateSeason::class)->execute($data)),
        ];
    }
}
