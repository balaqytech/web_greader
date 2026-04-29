<?php

namespace App\States\Applications\Transitions;

use App\Models\Application;
use App\States\Applications\UnderReview;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

class WaitingContractToUnderReview extends Transition
{
    public function __construct(
        private readonly Application $application,
        private readonly bool $signedByApplicant,
        private readonly ?string $filePath = null,
        private readonly ?string $signaturePath = null,
        private readonly ?int $transitionedBy = null,
        private readonly ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        $fromState = $this->application->status::$name;

        DB::transaction(function () use ($fromState) {
            $this->application->forceFill([
                'status' => UnderReview::$name,
                'contract_signed_at' => now(),
                'contract_signed_by_applicant' => $this->signedByApplicant,
                'contract_file_path' => $this->filePath,
                'contract_signature_path' => $this->signaturePath,
                'contract_token' => null,
                'contract_token_expires_at' => null,
            ])->save();

            $this->application->activities()->create([
                'transitioned_by' => $this->transitionedBy,
                'from_state' => $fromState,
                'to_state' => UnderReview::$name,
                'notes' => $this->notes,
                'transitioned_at' => now(),
            ]);
        });

        return $this->application->fresh();
    }
}
