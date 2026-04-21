<?php

namespace App\Filament\Pages;

use App\Enums\BotContactStatusEnum;
use App\Models\BotContact;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class BotContactsPage extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected string $view = 'filament.pages.bot-contacts';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.bot_contacts');
    }

    public function getTitle(): string | Htmlable
    {
        return __('admin.bot_contact.plural_label');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BotContact::query())
            ->recordTitleAttribute('whatsapp')
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.bot_contact.id')),
                TextColumn::make('channel')
                    ->label(__('admin.bot_contact.channel')),
                TextColumn::make('sender_name')
                    ->label(__('admin.bot_contact.sender_name')),
                TextColumn::make('whatsapp')
                    ->label(__('admin.bot_contact.whatsapp')),
                TextColumn::make('status')
                    ->label(__('admin.bot_contact.status'))
                    ->badge(),
                TextColumn::make('notes')
                    ->label(__('admin.bot_contact.notes')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.bot_contact.status'))
                    ->options(BotContactStatusEnum::class),
            ]);
    }
}
