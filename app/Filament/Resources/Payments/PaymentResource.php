<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\Schemas\PaymentInfolist;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Read-only by design: a Payment row is never hand-edited. It is created only by
 * `InitiatePaymentAction` and mutated only through the typed actions on this resource's table
 * (upload receipt, verify/reject a bank transfer, confirm cash) — each backed by its own
 * permission and a fresh Gate check inside its own mutation closure, never by a generic
 * create/edit form.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.financial_and_fees');
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
