<?php

declare(strict_types=1);

namespace App\Actions\Corrections;

use App\Actions\Contracts\BuildContractSnapshotAction;
use App\DTOs\Contracts\ContractSnapshot;
use App\Models\Application;
use App\Models\ApplicationContract;

/**
 * Decides whether a completed correction changed anything the signed contract depends on
 * (§6.2). Relevance is computed, never hand-picked: it recomputes the confirmed-minimum field
 * set and the resolved body from the *current* application + template and diffs both — plus the
 * template hash — against the active signed version's frozen snapshot.
 *
 * A change to the contract terms/template (template_hash) is relevant on its own; a change to
 * any confirmed-minimum field is relevant even if the current template never prints it; a
 * non-printed field (phone/address) that touches neither the minimum set nor the rendered body
 * is not relevant. Only the `meta.backfilled` marker is ignored during comparison — a backfilled
 * baseline is inherently uncertain, so it resolves conservatively to contract-relevant.
 */
final class ClassifyCorrectionAction
{
    public function __construct(
        private readonly BuildContractSnapshotAction $snapshotBuilder,
    ) {}

    public function isContractRelevant(Application $application, ?ApplicationContract $signedContract = null): bool
    {
        // Method form, not the `activeContract` dynamic property: this must never cache the
        // pre-regeneration version onto $application, whose relation the caller may read back
        // after a contract-relevant completion has already superseded it.
        $signedContract ??= $application->activeContract()->first();

        // No signed baseline to compare against — treat conservatively as relevant so a fresh
        // signature is required rather than silently returning to review.
        if ($signedContract === null) {
            return true;
        }

        $stored = $signedContract->data_snapshot ?? [];

        // A backfilled baseline is a best-effort reconstruction, not an authoritative record of
        // what was signed; any correction against it resolves conservatively to relevant.
        if ((bool) ($stored['meta']['backfilled'] ?? false)) {
            return true;
        }

        $current = $this->snapshotBuilder->handle($application);

        $storedMinimum = ContractSnapshot::normalizeMap($stored['minimum'] ?? []);

        if ($current->minimum !== $storedMinimum) {
            return true;
        }

        if ($current->templateHash !== (string) $signedContract->template_hash) {
            return true;
        }

        return $current->renderedBody !== (string) $signedContract->rendered_body;
    }
}
