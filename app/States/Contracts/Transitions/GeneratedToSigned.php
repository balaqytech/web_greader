<?php

declare(strict_types=1);

namespace App\States\Contracts\Transitions;

use App\Exceptions\ContractTransitionException;
use App\Models\ApplicationContract;
use App\States\Contracts\Signed;
use Spatie\ModelStates\Transition;

/**
 * Marks the active generated version signed. The signed artifact (signature timestamp and a
 * stored file) must already be persisted on the row by the driving action — this transition
 * refuses to flip the state without it, so "signed" always means "there is a signed copy".
 */
class GeneratedToSigned extends Transition
{
    public function __construct(public ApplicationContract $contract) {}

    public function handle(): ApplicationContract
    {
        if ($this->contract->signed_at === null || $this->contract->file_path === null) {
            throw ContractTransitionException::signedArtifactRequired($this->contract);
        }

        $this->contract->status = Signed::class;
        $this->contract->save();

        return $this->contract;
    }
}
