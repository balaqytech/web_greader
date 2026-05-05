<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\Actions\AcceptApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\CancelApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\CopyContractLinkFilamentAction;
use App\Filament\Resources\Applications\Actions\MoveToWaitingContractFilamentAction;
use App\Filament\Resources\Applications\Actions\OpenContractLinkFilamentAction;
use App\Filament\Resources\Applications\Actions\RejectApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\ReturnToSubmittedFilamentAction;
use App\Filament\Resources\Applications\Actions\SendContractFilamentAction;
use App\Filament\Resources\Applications\Actions\SubmitApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\UploadContractFilamentAction;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\States\Applications\Draft;
use App\States\Applications\Submitted;
use App\States\Applications\UnderReview;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewApplication extends ViewRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Data-entry stage ──────────────────────────────────
            EditAction::make()
                ->visible(
                    fn(Application $record): bool =>
                    $record->status instanceof Draft
                        || $record->status instanceof Submitted
                        || $record->status instanceof UnderReview
                ),
            SubmitApplicationFilamentAction::make(),
            MoveToWaitingContractFilamentAction::make(),
            ActionGroup::make([
                OpenContractLinkFilamentAction::make(),
                CopyContractLinkFilamentAction::make(),
                UploadContractFilamentAction::make(),
                SendContractFilamentAction::make(),
            ])
                ->label(__('admin.application.actions.contract_actions'))
                ->button(),
            ReturnToSubmittedFilamentAction::make(),
            AcceptApplicationFilamentAction::make(),

            // ReturnForCorrectionFilamentAction::make(),

            RejectApplicationFilamentAction::make(),
            CancelApplicationFilamentAction::make(),
        ];
    }
}
