<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\Actions\AcceptApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\CompleteApplicationDataFilamentAction;
use App\Filament\Resources\Applications\Actions\RejectApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\ReturnForCorrectionFilamentAction;
use App\Filament\Resources\Applications\Actions\RevertToDataCompleteFilamentAction;
use App\Filament\Resources\Applications\Actions\SendContractFilamentAction;
use App\Filament\Resources\Applications\Actions\UploadContractFilamentAction;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\States\Applications\PendingRegistration;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewApplication extends ViewRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (Application $record): bool => $record->status instanceof PendingRegistration),
            CompleteApplicationDataFilamentAction::make(),
            SendContractFilamentAction::make(),
            UploadContractFilamentAction::make(),
            RevertToDataCompleteFilamentAction::make(),
            AcceptApplicationFilamentAction::make(),
            RejectApplicationFilamentAction::make(),
            ReturnForCorrectionFilamentAction::make(),
        ];
    }
}
