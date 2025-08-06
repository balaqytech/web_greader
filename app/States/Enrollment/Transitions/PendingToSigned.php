<?php

namespace App\States\Enrollment\Transitions;

use App\Models\ProgramEnrollment;
use App\States\Enrollment\Pending;
use App\States\Enrollment\Signed;
use Spatie\ModelStates\Transition;

class PendingToSigned extends Transition
{
    public function __construct(
        public ProgramEnrollment $enrollment,
        public string $contract
    ) {
    }

    public function canTransition(): bool
    {
        return $this->enrollment->status->equals(Pending::class);
    }

    public function handle(): ProgramEnrollment
    {
        $this->enrollment->status = new Signed($this->enrollment);
        $this->enrollment->contract_pdf = $this->contract;
        $this->enrollment->contract_signed_at = now();
        $this->enrollment->save();

        return $this->enrollment;
    }
} 