<?php

declare(strict_types=1);

namespace App\States\Contracts\Transitions;

use App\Models\ApplicationContract;
use App\States\Contracts\Cancelled;
use Spatie\ModelStates\Transition;

/**
 * Shared behaviour for cancelling a version (from `generated` or `signed`) when its application
 * is cancelled: the token and expiry are invalidated so the link is dead, while the stored
 * artifacts are retained as a historical record. Terminal — a cancelled version never revives.
 */
abstract class AbstractCancelContract extends Transition
{
    public function __construct(public ApplicationContract $contract) {}

    public function handle(): ApplicationContract
    {
        $this->contract->status = Cancelled::class;
        $this->contract->token = null;
        $this->contract->token_expires_at = null;
        $this->contract->save();

        return $this->contract;
    }
}
