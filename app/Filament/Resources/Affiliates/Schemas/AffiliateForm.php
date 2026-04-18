<?php

namespace App\Filament\Resources\Affiliates\Schemas;

use App\Enums\AffiliateCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AffiliateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.affiliate.name'))
                    ->required(),
                Select::make('category')
                    ->label(__('admin.affiliate.category'))
                    ->options(AffiliateCategory::class)
                    ->required(),
                TextInput::make('whatsapp')
                    ->label(__('admin.affiliate.whatsapp'))
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('password')
                    ->label(__('admin.affiliate.password'))
                    ->password()
                    ->required(),
                TextInput::make('email')
                    ->label(__('admin.affiliate.email'))
                    ->email()
                    ->default(null),
                Textarea::make('notes')
                    ->label(__('admin.affiliate.notes'))
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
