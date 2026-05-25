<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Filament\Resources\Affiliates\AffiliateResource;
use App\Models\Lead;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.lead.lead_info'))
                    ->schema([
                        TextEntry::make('ref_no')
                            ->label(__('admin.lead.ref_no')),
                        TextEntry::make('branch.name')
                            ->label(__('admin.lead.branch'))
                            ->placeholder('-'),
                        TextEntry::make('season.name')
                            ->label(__('admin.lead.season')),
                        TextEntry::make('program_type')
                            ->label(__('admin.lead.program_type'))
                            ->badge(),
                        TextEntry::make('program.name')
                            ->label(__('admin.lead.program')),
                        TextEntry::make('whatsapp')
                            ->label(__('admin.lead.whatsapp')),
                        TextEntry::make('mother_phone')
                            ->label(__('admin.lead.mother_phone'))
                            ->placeholder('-'),
                        TextEntry::make('guardian_name')
                            ->label(__('admin.lead.guardian_name')),
                        TextEntry::make('student_name')
                            ->label(__('admin.lead.student_name')),
                        TextEntry::make('status')
                            ->label(__('admin.lead.status'))
                            ->badge()
                            ->color(fn (Lead $record) => $record->status->color())
                            ->formatStateUsing(fn (Lead $record) => $record->status->getLabel()),
                        TextEntry::make('source')
                            ->label(__('admin.lead.source'))
                            ->placeholder('-'),
                        TextEntry::make('affiliate.name')
                            ->label(__('admin.affiliate.label'))
                            ->placeholder('-')
                            ->visible(fn (Lead $record) => $record->affiliate_id !== null)
                            ->url(fn (Lead $record) => AffiliateResource::getUrl('view', ['record' => $record->affiliate])),
                        TextEntry::make('created_at')
                            ->label(__('admin.lead.created_at'))
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columnSpanFull()
                    ->columns(3),
                Section::make(__('admin.lead.data'))
                    ->schema(
                        fn (Lead $record) => collect($record->data)->map(function ($value, $key) {
                            return TextEntry::make($key)
                                ->label($key)
                                ->state($value);
                        })->toArray()
                    )
                    ->columnSpanFull()
                    ->columns(3),
            ]);
    }
}
