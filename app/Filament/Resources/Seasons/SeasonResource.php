<?php

namespace App\Filament\Resources\Seasons;

use App\Actions\Season\CloseSeason;
use App\Actions\Season\OpenSeason;
use App\Actions\Season\UpdateSeason;
use App\Enums\ProgramType;
use App\Filament\Resources\Seasons\Pages\ManageSeasons;
use App\Models\Season;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeasonResource extends Resource
{
    protected static ?string $model = Season::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.seasons');
    }

    public static function getModelLabel(): string
    {
        return __('admin.season.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.season.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.season.name'))
                    ->required(),
                Select::make('type')
                    ->label(__('admin.season.type'))
                    ->options(ProgramType::class)
                    ->required(),
                DatePicker::make('start_date')
                    ->label(__('admin.season.start_date')),
                DatePicker::make('end_date')
                    ->label(__('admin.season.end_date')),
                Toggle::make('is_registration_open')
                    ->label(__('admin.season.is_registration_open'))
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.season.name'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('admin.season.type'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('start_date')
                    ->label(__('admin.season.start_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label(__('admin.season.end_date'))
                    ->date()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('admin.season.is_active'))
                    ->boolean(),
                IconColumn::make('is_registration_open')
                    ->label(__('admin.season.is_registration_open'))
                    ->boolean(),
                IconColumn::make('is_closed')
                    ->label(__('admin.season.is_closed'))
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open'),
                TextColumn::make('created_at')
                    ->label(__('admin.season.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('admin.season.actions.open'))
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Season $record) => app(OpenSeason::class)->open($record))
                    ->hidden(fn (Season $record): bool => $record->is_active || $record->is_closed),
                Action::make('close')
                    ->label(__('admin.season.actions.close'))
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(fn (Season $record) => app(CloseSeason::class)->close($record))
                    ->hidden(fn (Season $record): bool => ! $record->is_active),
                EditAction::make()
                    ->using(fn (Season $record, array $data): Season => app(UpdateSeason::class)->update($record, $data)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSeasons::route('/'),
        ];
    }
}
