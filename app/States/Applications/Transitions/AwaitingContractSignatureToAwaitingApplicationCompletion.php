<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Actions\Documents\SyncRequiredDocumentsAction;
use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingContractSignature;
use App\States\Contracts\Superseded;
use App\Support\Applications\LockApplication;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Staff reopens data entry before signing. The row is locked and its persisted state
 * re-verified before writing, so a stale replay cannot supersede a version another request has
 * already used to sign. The active generated version is superseded (not erased or reused): its
 * token is invalidated and the row retained as history, so the next forward transition
 * generates a fresh vN+1. Contract supersession, application state, and activity are written in
 * one transaction.
 */
class AwaitingContractSignatureToAwaitingApplicationCompletion extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        return DB::transaction(function () {
            $application = LockApplication::inState($this->application, AwaitingContractSignature::class);

            $contract = $application->activeContract()->lockForUpdate()->first();

            if ($contract !== null) {
                $contract->status->transitionTo(Superseded::class);
            }

            $application->status = AwaitingApplicationCompletion::class;
            $application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $application,
                AwaitingContractSignature::$name,
                AwaitingApplicationCompletion::$name,
                $this->notes,
            );

            // Reopening data entry re-enters data completion, so re-run the idempotent sync:
            // it reconciles the requirement set (e.g. a transfer flag changed meanwhile)
            // without disturbing any document already uploaded.
            app(SyncRequiredDocumentsAction::class)->execute($application);

            return $application;
        }, attempts: 3);
    }
}
