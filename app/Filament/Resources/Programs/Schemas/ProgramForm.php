<?php

namespace App\Filament\Resources\Programs\Schemas;

use App\Enums\ProgramType;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.program.name'))
                    ->required(),
                Select::make('type')
                    ->label(__('admin.program.type'))
                    ->options(ProgramType::class)
                    ->required(),
                TextInput::make('base_price')
                    ->label(__('admin.program.base_price'))
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('ر.ع'),
                Toggle::make('accept_installments')
                    ->label(__('admin.program.accept_installments'))
                    ->default(false)
                    ->inline(false)
                    ->required(),
                Textarea::make('description')
                    ->label(__('admin.program.description'))
                    ->default(null)
                    ->columnSpanFull(),
                RichEditor::make('contract')
                    ->label(__('admin.program.contract'))
                    ->default(null)
                    ->columnSpanFull()
                    ->helperText(__('admin.program.contract_helper_text')),
                Toggle::make('is_open')
                    ->label(__('admin.program.is_open'))
                    ->default(true)
                    ->inline(false)
                    ->required(),
                Toggle::make('is_active')
                    ->label(__('admin.program.is_active'))
                    ->default(true)
                    ->inline(false)
                    ->required(),
                TextInput::make('sort_order')
                    ->label(__('admin.program.sort_order'))
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
