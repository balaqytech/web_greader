<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\Actions\AcceptApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\CancelApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\CopyContractLinkFilamentAction;
use App\Filament\Resources\Applications\Actions\MoveToWaitingContractFilamentAction;
use App\Filament\Resources\Applications\Actions\OpenContractLinkFilamentAction;
use App\Filament\Resources\Applications\Actions\RejectApplicationFilamentAction;
use App\Filament\Resources\Applications\Actions\ReturnToSubmittedFilamentAction;
use App\Filament\Resources\Applications\Actions\UploadContractFilamentAction;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
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
            // Editing is limited to the data-entry states. Once signed (branch review and
            // beyond) the record is immutable here until correction/versioning exists.
            EditAction::make()
                ->visible(
                    fn (Application $record): bool => $record->status instanceof AwaitingRegistrationFee
                        || $record->status instanceof AwaitingApplicationCompletion
                ),
            // No action advances the fee gate. SubmitApplicationFilamentAction used to sit
            // here and did exactly that, gated only by `Update:Application` — a permission
            // every branch staffer holds. It was inert only because the transition was
            // unregistered; registering it in the payments phase would have turned it into a
            // one-click way to skip paying. The gate is now crossed solely by a paid
            // registration-fee payment.
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
