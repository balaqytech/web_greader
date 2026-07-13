<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use Spatie\ModelStates\Transition;

class AwaitingContractSignatureToAwaitingBranchReview extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        $fromState = AwaitingContractSignature::$name;

        $this->application->status = AwaitingBranchReview::class;
        $this->application->save();

        app(RecordApplicationActivityAction::class)->handle(
            $this->application,
            $fromState,
            AwaitingBranchReview::$name,
            $this->notes,
        );

        return $this->application;
    }
}
