<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingContractSignature;
use Spatie\ModelStates\Transition;

/**
 * Staff reopens data entry before signing. Any generated (unsigned) contract token is
 * invalidated so a fresh contract is generated on the next forward transition.
 */
class AwaitingContractSignatureToAwaitingApplicationCompletion extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        $fromState = AwaitingContractSignature::$name;

        if ($this->application->contract) {
            $this->application->contract->update([
                'token' => null,
                'token_expires_at' => null,
                'signed_at' => null,
                'signed_by_applicant' => false,
            ]);
        }

        $this->application->status = AwaitingApplicationCompletion::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            AwaitingApplicationCompletion::$name,
            $this->notes,
        );

        return $this->application;
    }
}
