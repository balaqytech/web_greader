<?php

namespace App\States\Affiliates\Transitions;

use App\Models\Affiliate;
use App\Models\User;
use App\States\Affiliates\Verified;
use Spatie\ModelStates\Transition;

class RejectedToVerified extends Transition
{
    public function __construct(
        private readonly Affiliate $affiliate,
        private readonly User $verifiedBy,
    ) {}

    public function handle(): Affiliate
    {
        $this->affiliate->forceFill([
            'status' => Verified::$name,
            'verified_by' => $this->verifiedBy->id,
            'verified_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
        ])->save();

        return $this->affiliate->refresh();
    }
}
