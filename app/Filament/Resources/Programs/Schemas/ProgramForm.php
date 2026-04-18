<?php

namespace App\Filament\Resources\Programs\Schemas;

use App\Enums\ProgramType;
use App\Models\Branch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.program.program_info'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.program.name'))
                            ->required(),
                        Select::make('type')
                            ->label(__('admin.program.type'))
                            ->options(ProgramType::class)
                            ->required(),
                        DatePicker::make('min_birth_date')
                            ->label(__('admin.program.min_birth_date'))
                            ->required(),
                        DatePicker::make('max_birth_date')
                            ->label(__('admin.program.max_birth_date'))
                            ->required(),
                        Toggle::make('accept_installments')
                            ->label(__('admin.program.accept_installments'))
                            ->default(false)
                            ->inline(false)
                            ->required(),
                        Toggle::make('is_open')
                            ->label(__('admin.program.is_open'))
                            ->default(true)
                            ->inline(false)
                            ->required(),
                        Textarea::make('description')
                            ->label(__('admin.program.description'))
                            ->default(null)
                            ->columnSpanFull(),
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
                    ]),
                Section::make(__('admin.program.branches_info'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('branches')
                            ->label(__('admin.program.branches'))
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                Select::make('branch_id')
                                    ->label(__('admin.program.branch'))
                                    ->options(Branch::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                                TextInput::make('price')
                                    ->label(__('admin.program.branch_price'))
                                    ->required()
                                    ->numeric()
                                    ->default(0.0)
                                    ->prefix('ر.ع'),
                            ]),
                    ]),
                Section::make(__('admin.program.contract'))
                    ->columnSpanFull()
                    ->schema([
                        RichEditor::make('contract')
                            ->label(__('admin.program.contract'))
                            ->default(null)
                            ->columnSpanFull()
                            ->helperText(__('admin.program.contract_helper_text')),
                    ]),
            ]);
    }
}
