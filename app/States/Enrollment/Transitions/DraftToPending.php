<?php

namespace App\States\Enrollment\Transitions;

use App\Models\ProgramEnrollment;
use App\States\Enrollment\Draft;
use App\States\Enrollment\Pending;
use Illuminate\Support\Collection;
use Spatie\ModelStates\Transition;

class DraftToPending extends Transition
{
    public function __construct(
        public ProgramEnrollment $enrollment,
        public array|Collection $discounts
    ) {
    }

    public function canTransition(): bool
    {
        return $this->enrollment->status->equals(Draft::class);
    }

    public function handle(): ProgramEnrollment
    {
        $this->enrollment->status = new Pending($this->enrollment);
        $this->enrollment->discounts()->attach($this->discounts);
        $this->enrollment->save();

        return $this->enrollment;
    }
} 