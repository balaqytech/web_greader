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
use Illuminate\Validation\Rule;

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
                    ->label(__('admin.season.end_date'))
                    ->rule(Rule::date()->todayOrAfter()),
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
                TextColumn::make('closed_at')
                    ->label(__('admin.season.closed_at'))
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
                    ->icon('heroicon-o-lock-open')
                    ->action(fn(Season $record) => app(OpenSeason::class)->execute($record))
                    ->hidden(fn(Season $record): bool => $record->is_active || $record->closed_at),
                Action::make('close')
                    ->label(__('admin.season.actions.close'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-lock-closed')
                    ->action(fn(Season $record) => app(CloseSeason::class)->execute($record))
                    ->hidden(fn(Season $record): bool => ! $record->is_active),
                EditAction::make()
                    ->using(fn(Season $record, array $data): Season => app(UpdateSeason::class)->execute($record, $data)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSeasons::route('/'),
        ];
    }
}
