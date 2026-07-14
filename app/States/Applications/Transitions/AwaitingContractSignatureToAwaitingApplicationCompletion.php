<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingContractSignature;
use App\Support\Applications\LockApplication;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Staff reopens data entry before signing. The row is locked and its persisted state
 * re-verified before writing, so a stale replay cannot invalidate a token another request has
 * already used to sign. Any generated (unsigned) contract token is invalidated so a fresh
 * contract is generated on the next forward transition; contract update, application state,
 * and activity are written in one transaction.
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

            $contract = $application->contract()->lockForUpdate()->first();

            if ($contract !== null) {
                $contract->update([
                    'token' => null,
                    'token_expires_at' => null,
                    'signed_at' => null,
                    'signed_by_applicant' => false,
                ]);
            }

            $application->status = AwaitingApplicationCompletion::class;
            $application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $application,
                AwaitingContractSignature::$name,
                AwaitingApplicationCompletion::$name,
                $this->notes,
            );

            return $application;
        }, attempts: 3);
    }
}
