<?php

namespace App\Filament\Admin\Resources\ParentAccountResource\Pages;

use App\Filament\Admin\Resources\ParentAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewParentAccount extends ViewRecord
{
    protected static string $resource = ParentAccountResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
