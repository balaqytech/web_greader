<?php

declare(strict_types=1);

namespace App\States\Contracts\Transitions;

use App\Events\ContractSigned;
use App\Exceptions\ContractTransitionException;
use App\Models\Application;
use App\Models\ApplicationContract;
use App\States\Contracts\Signed;
use Illuminate\Support\Facades\DB;
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

        // Both signing paths (online applicant, staff upload) call this inside their own
        // transaction, but wrapping here too guarantees the state flip and the outbox row are
        // atomic even for a hypothetical standalone caller. Nested transactions are savepoints,
        // so this is a no-op cost when already enclosed.
        return DB::transaction(function () {
            $this->contract->status = Signed::class;
            $this->contract->save();

            // Scope-less lookup: the signer/staff context may not share the application's branch.
            $application = Application::withoutGlobalScopes()->find($this->contract->application_id);

            // No token, signature, or artifact path is carried on the event.
            event(new ContractSigned(
                $this->contract->getKey(),
                (int) $this->contract->application_id,
                $application?->ref_no,
                $application?->branch_id,
                (int) $this->contract->version,
                (bool) $this->contract->signed_by_applicant,
            ));

            return $this->contract;
        });
    }
}
