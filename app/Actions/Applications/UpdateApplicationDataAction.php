<?php

namespace App\Actions\Applications;

use App\Actions\Documents\SyncRequiredDocumentsAction;
use App\DTOs\Application\UpdateApplicationDataDTO;
use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use Illuminate\Support\Facades\DB;

final class UpdateApplicationDataAction
{
    public function __construct(
        private readonly SyncRequiredDocumentsAction $syncRequiredDocuments,
    ) {}

    /**
     * Update the application's registration data fields.
     *
     * When the transfer flag is toggled while the application is already awaiting completion,
     * the document requirement set is resynchronised in the same transaction so the transfer
     * file appears or is retired to match — without ever discarding history for a student who
     * later stops transferring.
     */
    public function execute(Application $application, UpdateApplicationDataDTO $dto): Application
    {
        return DB::transaction(function () use ($application, $dto): Application {
            $application->fill($dto->toArray());

            $transferFlagChanged = $application->isDirty('is_transfer_student');

            $application->save();

            if ($transferFlagChanged && $application->status instanceof AwaitingApplicationCompletion) {
                $this->syncRequiredDocuments->execute($application);
            }

            return $application->fresh();
        });
    }
}
