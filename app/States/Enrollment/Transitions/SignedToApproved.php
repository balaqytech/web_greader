<?php

namespace App\States\Enrollment\Transitions;

use App\Models\ProgramEnrollment;
use App\States\Enrollment\Signed;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;
use App\States\Enrollment\Approved;

class SignedToApproved extends Transition
{
    public function __construct(
        public ProgramEnrollment $enrollment,
        public array $installments
    ) {}

    public function canTransition(): bool
    {
        return $this->enrollment->status->equals(Signed::class);
    }

    public function handle(): ProgramEnrollment
    {
        DB::transaction(function () {
            $this->enrollment->status = new Approved($this->enrollment);
            $this->enrollment->save();

            $this->enrollment->installments()->createMany($this->installments);

            $this->enrollment->invoice()->create([
                'amount' => $this->enrollment->final_price,
            ]);
        });

        return $this->enrollment;
    }
}
