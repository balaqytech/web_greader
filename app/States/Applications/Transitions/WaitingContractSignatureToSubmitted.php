<?php

namespace App\States\Applications\Transitions;

use App\Models\Application;
use App\States\Applications\Submitted;
use Spatie\ModelStates\Transition;

class WaitingContractSignatureToSubmitted extends Transition
{
    public function __construct(public Application $application) {}

    public function handle(): Application
    {
        // Invalidate old contract token if needed
        $this->application->contract_token = null;
        $this->application->contract_token_expires_at = null;
        $this->application->contract_signed_at = null;
        $this->application->contract_signed_by_applicant = false;

        $this->application->status = Submitted::class;
        $this->application->save();

        return $this->application;
    }
}
