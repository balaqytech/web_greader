<?php

declare(strict_types=1);

namespace App\States\Contracts\Transitions;

use App\Models\ApplicationContract;
use App\States\Contracts\Superseded;
use Carbon\CarbonImmutable;
use Spatie\ModelStates\Transition;

/**
 * Shared behaviour for superseding a version (from `generated` or `signed`): the token and its
 * expiry are invalidated so the old link can never be signed again, the supersession is
 * timestamped, and — when a successor is supplied by the regenerating action — the row is
 * linked forward to it. The stored artifacts (`file_path`, `signature_path`, `rendered_body`,
 * `data_snapshot`, `template_hash`) are deliberately retained: superseding is a version bump,
 * not a deletion, and the historical record of what was generated/signed must survive.
 */
abstract class AbstractSupersedeContract extends Transition
{
    public function __construct(
        public ApplicationContract $contract,
        public ?int $supersededByContractId = null,
    ) {}

    public function handle(): ApplicationContract
    {
        $this->contract->status = Superseded::class;
        $this->contract->token = null;
        $this->contract->token_expires_at = null;
        $this->contract->superseded_at = CarbonImmutable::now();
        $this->contract->superseded_by_contract_id = $this->supersededByContractId;
        $this->contract->save();

        return $this->contract;
    }
}
