<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Models\Application;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContractSignature;
use Spatie\ModelStates\Transition;

class WaitingContractSignatureToUnderReview extends Transition
{
    public function __construct(public Application $application) {}

    public function handle(): Application
    {
        if (! $this->application->contract || ! $this->application->contract->isSigned()) {
            throw new \Exception('Application contract must be signed before review.');
        }

        $fromState = WaitingContractSignature::getMorphClass();

        $this->application->status = UnderReview::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            UnderReview::getMorphClass(),
        );

        return $this->application;
    }
}
