<?php

namespace App\States\Affiliates\Transitions;

use App\Models\Affiliate;
use App\Models\User;
use App\States\Affiliates\Verified;
use Spatie\ModelStates\Transition;

class PendingToVerified extends Transition
{
    public function __construct(
        private readonly Affiliate $affiliate,
        private readonly User $verifiedBy,
    ) {}

    public function handle(): Affiliate
    {
        $this->affiliate->forceFill([
            'code' => $this->affiliate->generateUniqueCode($this->affiliate->name),
            'status' => Verified::$name,
            'verified_by' => $this->verifiedBy->id,
            'verified_at' => now(),
        ])->save();

        return $this->affiliate->refresh();
    }
}
