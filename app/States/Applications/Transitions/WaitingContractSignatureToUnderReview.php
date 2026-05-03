<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Models\Application;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContractSignature;
use Spatie\ModelStates\Transition;

class WaitingContractSignatureToUnderReview extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        if (! $this->application->contract || ! $this->application->contract->isSigned()) {
            throw new \Exception(__('alerts.application.application_contract_is_not_signed'));
        }

        $fromState = WaitingContractSignature::class;

        $this->application->status = UnderReview::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            UnderReview::class,
            $this->notes,
        );

        return $this->application;
    }
}
