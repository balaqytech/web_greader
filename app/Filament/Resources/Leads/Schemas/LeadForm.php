<?php

namespace App\Filament\Resources\Leads\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->label(__('admin.branch.label'))
                    ->relationship('branch', 'name')
                    ->default(null),
                Select::make('program_id')
                    ->label(__('admin.program.label'))
                    ->relationship('program', 'name')
                    ->required(),
                TextInput::make('whatsapp')
                    ->label(__('admin.lead.whatsapp'))
                    ->required(),
                TextInput::make('mother_phone')
                    ->label(__('admin.lead.mother_phone')),
                TextInput::make('guardian_name')
                    ->label(__('admin.lead.guardian_name'))
                    ->required(),
                TextInput::make('student_name')
                    ->label(__('admin.lead.student_name'))
                    ->required(),
                Select::make('affiliate_id')
                    ->label(__('admin.affiliate.label'))
                    ->relationship('affiliate', 'name')
                    ->helperText('admin.optional')
                    ->default(null),
                KeyValue::make('data')
                    ->label(__('admin.lead.data'))
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
