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
        $fromState = WaitingContractSignature::$name;

        $this->application->status = UnderReview::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            UnderReview::$name,
            $this->notes,
        );

        return $this->application;
    }
}
