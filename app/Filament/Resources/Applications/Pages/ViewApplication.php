<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\Actions\AcceptApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\CancelApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\CopyContractLinkFilamentAction;
use App\Filament\Resources\Applications\Actions\MoveToWaitingContractFilamentAction;
use App\Filament\Resources\Applications\Actions\OpenContractLinkFilamentAction;
use App\Filament\Resources\Applications\Actions\RejectApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\ReturnToSubmittedFilamentAction;
use App\Filament\Resources\Applications\Actions\SubmitApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\UploadContractFilamentAction;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingRegistrationFee;
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
                    fn (Application $record): bool => $record->status instanceof AwaitingRegistrationFee
                        || $record->status instanceof AwaitingApplicationCompletion
                        || $record->status instanceof AwaitingBranchReview
                ),
            SubmitApplicationFilamentAction::make(),
            MoveToWaitingContractFilamentAction::make(),
            ActionGroup::make([
                OpenContractLinkFilamentAction::make(),
                CopyContractLinkFilamentAction::make(),
                UploadContractFilamentAction::make(),
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
