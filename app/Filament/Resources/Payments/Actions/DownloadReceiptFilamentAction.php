<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Actions;

use App\Models\Payment;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Gate;

class DownloadReceiptFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'download_receipt';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.payment.actions.download_receipt'));
        $this->icon('heroicon-o-arrow-down-tray');
        $this->color('gray');
        $this->visible(fn (?Payment $record): bool => $record instanceof Payment
            && is_string($record->receipt_path)
            && $record->receipt_path !== ''
            && Gate::allows('viewReceipt', $record));
        $this->url(
            fn (Payment $record): string => route('payments.receipt.download', $record),
            shouldOpenInNewTab: true,
        );
    }
}
