<?php

namespace App\Filament\Resources\Affiliates\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AffiliateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.affiliate.affiliate_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('name')
                            ->label(__('admin.affiliate.name')),
                        TextEntry::make('code')
                            ->label(__('admin.affiliate.code')),
                        TextEntry::make('category')
                            ->label(__('admin.affiliate.category'))
                            ->badge(),
                        TextEntry::make('whatsapp')
                            ->label(__('admin.affiliate.whatsapp')),
                        TextEntry::make('email')
                            ->label(__('admin.affiliate.email'))
                            ->label('Email address')
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->label(__('admin.affiliate.status'))
                            ->badge()
                            ->color(fn($state) => $state->color())
                            ->formatStateUsing(fn($state) => $state->getLabel()),
                        TextEntry::make('verifiedBy.name')
                            ->label(__('admin.affiliate.verified_by'))
                            ->visible(fn($record) => $record->verified_by),
                        TextEntry::make('verified_at')
                            ->label(__('admin.affiliate.verified_at'))
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn($record) => $record->verified_at),
                        TextEntry::make('rejectedBy.name')
                            ->label(__('admin.affiliate.rejected_by'))
                            ->visible(fn($record) => $record->rejected_by),
                        TextEntry::make('rejected_at')
                            ->label(__('admin.affiliate.rejected_at'))
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn($record) => $record->rejected_at),
                        TextEntry::make('creation_source')
                            ->label(__('admin.affiliate.creation_source'))
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label(__('admin.lead.created_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('notes')
                            ->label(__('admin.affiliate.notes'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
