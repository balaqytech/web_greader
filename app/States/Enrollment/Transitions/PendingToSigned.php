<?php

namespace App\States\Enrollment\Transitions;

use App\Models\ProgramEnrollment;
use App\States\Enrollment\Signed;
use App\States\Enrollment\Pending;
use Spatie\ModelStates\Transition;
use Illuminate\Support\Facades\Mail;
use App\Mail\StudentSignedContractMail;

class PendingToSigned extends Transition
{
    public function __construct(
        public ProgramEnrollment $enrollment,
        public string $contract
    ) {}

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

        Mail::to($this->enrollment->student->parentAccount->email)->send(new StudentSignedContractMail($this->enrollment));

        return $this->enrollment;
    }
}
