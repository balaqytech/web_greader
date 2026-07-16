<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\Actions\ConfirmCashFilamentAction;
use App\Filament\Resources\Payments\Actions\DownloadReceiptFilamentAction;
use App\Filament\Resources\Payments\Actions\RejectBankTransferFilamentAction;
use App\Filament\Resources\Payments\Actions\UploadReceiptFilamentAction;
use App\Filament\Resources\Payments\Actions\VerifyBankTransferFilamentAction;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadReceiptFilamentAction::make(),
            UploadReceiptFilamentAction::make(),
            VerifyBankTransferFilamentAction::make(),
            RejectBankTransferFilamentAction::make(),
            ConfirmCashFilamentAction::make(),
        ];
    }
}
