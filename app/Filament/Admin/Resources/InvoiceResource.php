<?php

namespace App\Filament\Admin\Resources;

use App\Enums\InvoiceStatus;
use App\Filament\Admin\Resources\InvoiceResource\Pages;
use App\Filament\Admin\Resources\InvoiceResource\RelationManagers;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.invoices');
    }

    public static function getModelLabel(): string
    {
        return __('admin.invoice.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.invoice.plural_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label(__('admin.invoice.number'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('student.full_name')
                    ->label(__('admin.student.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('admin.invoice.total_amount'))
                    ->money('OMR'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label(__('admin.invoice.due_date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.invoice.status'))
                    ->badge()
                    ->color(fn($state) => $state->color()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(InvoiceStatus::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }
}
