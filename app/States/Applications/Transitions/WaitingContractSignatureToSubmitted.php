<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Models\Application;
use App\States\Applications\Submitted;
use App\States\Applications\WaitingContractSignature;
use Spatie\ModelStates\Transition;

class WaitingContractSignatureToSubmitted extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        $fromState = WaitingContractSignature::$name;

        // Invalidate old contract data via the ApplicationContract model
        if ($this->application->contract) {
            $this->application->contract->update([
                'token' => null,
                'token_expires_at' => null,
                'signed_at' => null,
                'signed_by_applicant' => false,
            ]);
        }

        $this->application->status = Submitted::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            Submitted::$name,
            $this->notes,
        );

        return $this->application;
    }
}
