<?php

namespace App\States\Affiliates\Transitions;

use App\Models\Affiliate;
use App\Models\User;
use App\States\Affiliates\Rejected;
use Spatie\ModelStates\Transition;

class ToRejected extends Transition
{
    public function __construct(
        private readonly Affiliate $affiliate,
        private readonly User $rejectedBy,
    ) {}

    public function handle(): Affiliate
    {
        $this->affiliate->forceFill([
            'status' => Rejected::$name,
            'rejected_by' => $this->rejectedBy->id,
            'rejected_at' => now(),
        ])->save();

        return $this->affiliate->refresh();
    }
}
