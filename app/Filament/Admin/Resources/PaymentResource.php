<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\PaymentResource\Pages;
use App\Filament\Admin\Resources\PaymentResource\RelationManagers;
use App\Models\Payment;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.payments');
    }

    public static function getModelLabel(): string
    {
        return __('admin.payment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.payment.plural_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_id')
                    ->label(__('admin.payment.invoice_id')),
                Tables\Columns\TextColumn::make('transaction_id')
                    ->label(__('admin.payment.transaction_id'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('method')
                    ->label(__('admin.payment.method')),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('admin.payment.amount')),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label(__('admin.payment.payment_date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.payment.status'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.payment.status'))
                    ->options(PaymentStatus::class)
                    ->default(PaymentStatus::PAID->value),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('admin.payment.payment_info'))
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('invoice.number')
                            ->label(__('admin.payment.invoice_number')),
                        Infolists\Components\TextEntry::make('transaction_id')
                            ->label(__('admin.payment.transaction_id')),
                        Infolists\Components\TextEntry::make('method')
                            ->label(__('admin.payment.method')),
                        Infolists\Components\TextEntry::make('amount')
                            ->label(__('admin.payment.amount')),
                        Infolists\Components\TextEntry::make('payment_date')
                            ->label(__('admin.payment.payment_date'))
                            ->date(),
                        Infolists\Components\TextEntry::make('status')
                            ->label(__('admin.payment.status')),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
