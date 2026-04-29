<?php

namespace App\Filament\Resources\Guardians\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class GuardianInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('admin.guardian.name')),
                TextEntry::make('phone')
                    ->label(__('admin.guardian.phone')),
                TextEntry::make('email')
                    ->label(__('admin.guardian.email'))
                    ->placeholder('-'),
                TextEntry::make('id_number')
                    ->label(__('admin.guardian.id_number'))
                    ->placeholder('-'),
                TextEntry::make('occupation')
                    ->label(__('admin.guardian.occupation'))
                    ->placeholder('-'),
                TextEntry::make('work_address')
                    ->label(__('admin.guardian.work_address'))
                    ->placeholder('-'),
                TextEntry::make('work_phone')
                    ->label(__('admin.guardian.work_phone'))
                    ->placeholder('-'),
                TextEntry::make('relationship')
                    ->label(__('admin.guardian.relationship'))
                    ->badge(),
            ]);
    }
}
