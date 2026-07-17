<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Scopes\BranchScope;
use App\States\Documents\Missing;
use Illuminate\Support\Facades\DB;

/**
 * Materialises the fixed global document requirement set onto an application.
 *
 * The requirement set is not configurable per program or branch: every application needs the
 * eight base documents, and a transfer student additionally needs the transfer file — nine in
 * total. This action is the single writer of {@see ApplicationDocument} requirement rows.
 *
 * It is idempotent by construction. Rows are keyed uniquely on `(application_id, type)`, so a
 * re-run finds the existing row and only ever re-flags `is_required` — it never resets a
 * requirement's lifecycle state, never touches its file history, and never deletes a row.
 * That is what makes it safe to call on every entry into data-completion and again whenever
 * the transfer flag is toggled.
 *
 * The transfer file is the only requirement whose presence depends on application data. When a
 * student stops being a transfer student the row (and its uploaded history) is retained and
 * merely marked optional, so toggling the flag back on reactivates the same row rather than
 * creating a second one and orphaning any file already uploaded against it.
 *
 * The application is locked for the duration so two concurrent entries into data-completion
 * cannot both decide a row is absent and race to create it (the unique index is the ultimate
 * backstop, but the lock keeps the common path from ever tripping it).
 */
final class SyncRequiredDocumentsAction
{
    public function execute(Application $application): void
    {
        DB::transaction(function () use ($application): void {
            $locked = Application::withoutGlobalScope(BranchScope::class)
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            foreach (DocumentType::cases() as $type) {
                $this->syncType($locked, $type);
            }
        });
    }

    private function syncType(Application $application, DocumentType $type): void
    {
        $existing = ApplicationDocument::withoutGlobalScope(BranchScope::class)
            ->where('application_id', $application->getKey())
            ->where('type', $type)
            ->first();

        if ($type->isTransferOnly()) {
            $this->syncTransferFile($application, $type, $existing);

            return;
        }

        // Base requirements are always required; create if missing, otherwise leave the
        // lifecycle untouched but keep the required flag and requirement group authoritative.
        if ($existing === null) {
            $this->createRequirement($application, $type, isRequired: true);

            return;
        }

        if (! $existing->is_required || $existing->requirement_group !== $type->getRequirementGroup()) {
            $existing->forceFill([
                'is_required' => true,
                'requirement_group' => $type->getRequirementGroup(),
            ])->save();
        }
    }

    private function syncTransferFile(Application $application, DocumentType $type, ?ApplicationDocument $existing): void
    {
        $isRequired = (bool) $application->is_transfer_student;

        if ($existing === null) {
            // A non-transfer student gets no transfer-file row at all: that is what keeps the
            // requirement set at eight rows for them. Reactivating the flag later creates it.
            if ($isRequired) {
                $this->createRequirement($application, $type, isRequired: true);
            }

            return;
        }

        // The row already exists — never delete it, only re-flag. A student who is no longer
        // transferring keeps the row (and any uploaded history) as an optional requirement.
        if ($existing->is_required !== $isRequired) {
            $existing->forceFill(['is_required' => $isRequired])->save();
        }
    }

    private function createRequirement(Application $application, DocumentType $type, bool $isRequired): void
    {
        ApplicationDocument::create([
            'application_id' => $application->getKey(),
            // Denormalised from the locked application, never from a request.
            'branch_id' => $application->branch_id,
            'type' => $type,
            'status' => Missing::$name,
            'is_required' => $isRequired,
            'requirement_group' => $type->getRequirementGroup(),
        ]);
    }
}
