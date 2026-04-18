<?php

namespace App\Filament\Resources\Affiliates\RelationManagers;

use App\Filament\Resources\Leads\LeadResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $relatedResource = LeadResource::class;

    public function table(Table $table): Table
    {
        return $table;
    }
}
