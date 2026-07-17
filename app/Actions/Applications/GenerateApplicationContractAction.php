<?php

namespace App\Actions\Applications;

use App\Actions\Contracts\BuildContractSnapshotAction;
use App\Models\Application;
use App\Models\ApplicationContract;
use App\States\Contracts\Generated;
use App\States\Contracts\Superseded;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates the next immutable contract version (§3.5, §5.5). Lock order is application →
 * contracts: the caller must already hold the application row lock (every transition that calls
 * this does), then every existing version is locked here before any write, so two concurrent
 * generations serialize and cannot both allocate the same version number or leave two active
 * versions.
 *
 * The sequence is: lock all versions → supersede any active (`generated`/`signed`) one →
 * allocate `max(version)+1` → create the new `generated` version from a fresh snapshot →
 * link each predecessor forward to it. Any failure rolls the whole thing back with the
 * enclosing transaction, so a half-superseded predecessor can never be left behind.
 */
class GenerateApplicationContractAction
{
    public function handle(Application $application): ApplicationContract
    {
        return DB::transaction(function () use ($application) {
            /** @var Collection<int, ApplicationContract> $versions */
            $versions = $application->contracts()->lockForUpdate()->get();

            $active = $versions->filter(fn (ApplicationContract $contract): bool => $contract->status->isActive());

            $nextVersion = (int) ($versions->max('version') ?? 0) + 1;

            $snapshot = app(BuildContractSnapshotAction::class)->handle($application);

            $contract = $application->contracts()->create([
                'version' => $nextVersion,
                'status' => Generated::class,
                'data_snapshot' => $snapshot->toArray(),
                'rendered_body' => $snapshot->renderedBody,
                'template_hash' => $snapshot->templateHash,
                'generated_by' => Auth::id(),
                'token' => Str::random(64),
                'token_expires_at' => now()->addDays(7),
            ]);

            foreach ($active as $predecessor) {
                $predecessor->status->transitionTo(Superseded::class, $contract->id);
            }

            return $contract;
        });
    }
}
