<?php

namespace App\Filament\Resources\Guardians\Schemas;

use App\Enums\GuardianRelationship;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GuardianForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.guardian.name'))
                    ->required(),
                TextInput::make('phone')
                    ->label(__('admin.guardian.phone'))
                    ->tel()
                    ->required(),
                TextInput::make('email')
                    ->label(__('admin.guardian.email'))
                    ->email()
                    ->default(null),
                TextInput::make('id_number')
                    ->label(__('admin.guardian.id_number'))
                    ->default(null),
                TextInput::make('occupation')
                    ->label(__('admin.guardian.occupation'))
                    ->default(null),
                TextInput::make('work_address')
                    ->label(__('admin.guardian.work_address'))
                    ->default(null),
                TextInput::make('work_phone')
                    ->label(__('admin.guardian.work_phone'))
                    ->tel()
                    ->default(null),
            ]);
    }
}
