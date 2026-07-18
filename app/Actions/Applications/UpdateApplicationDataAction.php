<?php

namespace App\Actions\Applications;

use App\Actions\Documents\SyncRequiredDocumentsAction;
use App\DTOs\Application\UpdateApplicationDataDTO;
use App\Exceptions\StaleApplicationStateException;
use App\Models\Application;
use App\Models\Scopes\BranchScope;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\CorrectionRequested;
use App\Support\Applications\ApplicationEditability;
use Illuminate\Support\Facades\DB;

/**
 * Applies a data edit to an application under its row lock (§1 corrective).
 *
 * The application is re-read with a `FOR UPDATE` lock and its persisted editability re-verified
 * before the DTO is applied, so a stale caller cannot commit an edit after a concurrent request
 * has already completed a correction, generated a contract, or otherwise moved the application
 * out of an editable state. The change is applied to the freshly locked instance — never to the
 * caller's possibly-stale one.
 *
 * A transfer-flag change is reconciled into the document requirement set inside the same
 * transaction, in both `AwaitingApplicationCompletion` and `CorrectionRequested` — so a
 * correction that toggles the transfer flag never leaves the requirements stale, and no caller
 * (Filament today, the Phase 5 API later) has to remember to resync afterwards. Uploaded
 * document history is preserved (see SyncRequiredDocumentsAction).
 */
final class UpdateApplicationDataAction
{
    public function execute(Application $application, UpdateApplicationDataDTO $dto): Application
    {
        return DB::transaction(function () use ($application, $dto): Application {
            $locked = Application::withoutGlobalScope(BranchScope::class)
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! ApplicationEditability::isEditable($locked)) {
                throw StaleApplicationStateException::make(
                    $application,
                    'editable',
                    $locked?->status ?? new \stdClass,
                );
            }

            $locked->fill($dto->toArray());

            $transferFlagChanged = $locked->isDirty('is_transfer_student');

            $locked->save();

            $reconcilableState = $locked->status instanceof AwaitingApplicationCompletion
                || $locked->status instanceof CorrectionRequested;

            if ($transferFlagChanged && $reconcilableState) {
                app(SyncRequiredDocumentsAction::class)->execute($locked);
            }

            return $locked->fresh();
        }, attempts: 3);
    }
}
