<?php

namespace App\Filament\Resources\Affiliates\Pages;

use App\Enums\Source;
use App\Filament\Resources\Affiliates\AffiliateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAffiliate extends CreateRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creation_source'] = Source::DASHBOARD;

        return $data;
    }
}
