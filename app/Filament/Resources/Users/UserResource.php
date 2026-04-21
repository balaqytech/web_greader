<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Users;

    protected static ?string $recordTitleAttribute = 'email';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.roles_and_permissions');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.users');
    }

    public static function getModelLabel(): string
    {
        return __('admin.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.user.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.user.name'))
                    ->required(),
                TextInput::make('email')
                    ->label(__('admin.user.email'))
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->label(__('admin.user.password'))
                    ->password()
                    ->required(),
                Select::make('role')
                    ->label(__('admin.user.role'))
                    ->options(Role::all()->pluck('name', 'name')->toArray())
                    ->required(),
                Select::make('branch_id')
                    ->label(__('admin.user.branch'))
                    ->relationship('branch', 'name')
                    ->helperText(__('admin.user.branch_helper_text')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.user.name'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('admin.user.email'))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('admin.user.role'))
                    ->state(fn(User $record): string => $record->getRoleNames()->first() ?? ''),
                TextColumn::make('created_at')
                    ->label(__('admin.user.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('branch.name')
                    ->label(__('admin.user.branch'))
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->after(function (User $record, $data) {
                        $record->roles()->detach();
                        $record->assignRole($data['role']);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
