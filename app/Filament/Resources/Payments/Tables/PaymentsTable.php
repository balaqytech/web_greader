<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Resources\Payments\Actions\ConfirmCashFilamentAction;
use App\Filament\Resources\Payments\Actions\RejectBankTransferFilamentAction;
use App\Filament\Resources\Payments\Actions\UploadReceiptFilamentAction;
use App\Filament\Resources\Payments\Actions\VerifyBankTransferFilamentAction;
use App\Models\Payment;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['application', 'branch']))
            ->columns([
                TextColumn::make('reference')
                    ->label(__('admin.payment.reference'))
                    ->searchable(),
                TextColumn::make('application.ref_no')
                    ->label(__('admin.payment.application'))
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label(__('admin.payment.branch'))
                    ->searchable(),
                TextColumn::make('method')
                    ->label(__('admin.payment.method'))
                    ->badge(),
                TextColumn::make('amount')
                    ->label(__('admin.payment.amount'))
                    ->formatStateUsing(fn (Payment $record): string => "{$record->amount} {$record->currency}"),
                TextColumn::make('status')
                    ->label(__('admin.payment.status'))
                    ->badge()
                    ->color(fn (Payment $record) => $record->status->getColor())
                    ->formatStateUsing(fn (Payment $record) => $record->status->getLabel()),
                TextColumn::make('created_at')
                    ->label(__('admin.payment.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->label(__('admin.payment.method'))
                    ->options([
                        'thawani' => __('admin.payment.methods.thawani'),
                        'bank_transfer' => __('admin.payment.methods.bank_transfer'),
                        'cash' => __('admin.payment.methods.cash'),
                    ]),
                SelectFilter::make('status')
                    ->label(__('admin.payment.status'))
                    ->options([
                        'pending' => __('admin.payment.states.pending'),
                        'awaiting_verification' => __('admin.payment.states.awaiting_verification'),
                        'paid' => __('admin.payment.states.paid'),
                        'failed' => __('admin.payment.states.failed'),
                        'rejected' => __('admin.payment.states.rejected'),
                        'expired' => __('admin.payment.states.expired'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                UploadReceiptFilamentAction::make(),
                VerifyBankTransferFilamentAction::make(),
                RejectBankTransferFilamentAction::make(),
                ConfirmCashFilamentAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
