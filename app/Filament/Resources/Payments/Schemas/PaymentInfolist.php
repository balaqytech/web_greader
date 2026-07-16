<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Payment;
use App\Support\Payments\PaymentApplicationProjection;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextEntry::make('reference')
                    ->label(__('admin.payment.reference')),
                TextEntry::make(PaymentApplicationProjection::APPLICATION_REFERENCE)
                    ->label(__('admin.payment.application'))
                    ->placeholder('-'),
                TextEntry::make(PaymentApplicationProjection::STUDENT_NAME)
                    ->label(__('admin.application.student_name'))
                    ->placeholder('-'),
                TextEntry::make(PaymentApplicationProjection::PROGRAM_NAME)
                    ->label(__('admin.application.program'))
                    ->placeholder('-'),
                TextEntry::make(PaymentApplicationProjection::BRANCH_NAME)
                    ->label(__('admin.payment.branch'))
                    ->placeholder('-'),
                TextEntry::make('method')
                    ->label(__('admin.payment.method'))
                    ->badge(),
                TextEntry::make('status')
                    ->label(__('admin.payment.status'))
                    ->badge()
                    ->color(fn (Payment $record) => $record->status->getColor())
                    ->formatStateUsing(fn (Payment $record) => $record->status->getLabel()),
                TextEntry::make('amount')
                    ->label(__('admin.payment.amount'))
                    ->formatStateUsing(fn (Payment $record): string => "{$record->amount} {$record->currency}"),
                TextEntry::make('provider_checkout_url')
                    ->label(__('admin.payment.checkout_url'))
                    ->copyable()
                    ->url(fn (Payment $record): ?string => $record->safeCheckoutUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (Payment $record): bool => $record->safeCheckoutUrl() !== null),
                TextEntry::make('receipt_path')
                    ->label(__('admin.payment.receipt'))
                    ->formatStateUsing(fn (): string => __('admin.payment.receipt_attached'))
                    ->placeholder('-')
                    ->visible(fn (Payment $record): bool => $record->receipt_path !== null),
                TextEntry::make('failure_reason')
                    ->label(__('admin.payment.failure_reason'))
                    ->placeholder('-')
                    ->visible(fn (Payment $record): bool => $record->failure_reason !== null),
                TextEntry::make('rejection_reason')
                    ->label(__('admin.payment.rejection_reason'))
                    ->placeholder('-')
                    ->visible(fn (Payment $record): bool => $record->rejection_reason !== null),
                TextEntry::make('cash_reference')
                    ->label(__('admin.payment.cash_reference'))
                    ->placeholder('-')
                    ->visible(fn (Payment $record): bool => $record->cash_reference !== null),
                TextEntry::make('cash_notes')
                    ->label(__('admin.payment.cash_notes'))
                    ->placeholder('-')
                    ->visible(fn (Payment $record): bool => $record->cash_notes !== null),
                TextEntry::make('verifiedBy.name')
                    ->label(__('admin.payment.verified_by'))
                    ->placeholder('-'),
                TextEntry::make('verified_at')
                    ->label(__('admin.payment.verified_at'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('createdBy.name')
                    ->label(__('admin.payment.created_by'))
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label(__('admin.payment.created_at'))
                    ->dateTime(),
            ]);
    }
}
