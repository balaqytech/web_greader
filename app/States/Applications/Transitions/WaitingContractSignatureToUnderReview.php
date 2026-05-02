<?php

namespace App\States\Applications\Transitions;

use App\Models\Application;
use App\States\Applications\UnderReview;
use Spatie\ModelStates\Transition;

class WaitingContractSignatureToUnderReview extends Transition
{
    public function __construct(public Application $application) {}

    public function handle(): Application
    {
        if (! $this->application->contract || ! $this->application->contract->isSigned()) {
            throw new \Exception('Application contract must be signed before review.');
        }

        $this->application->status = UnderReview::class;
        $this->application->save();

        return $this->application;
    }
}
