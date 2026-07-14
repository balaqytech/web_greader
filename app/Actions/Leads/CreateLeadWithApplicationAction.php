<?php

namespace App\Actions\Leads;

use App\Actions\Applications\CreateApplicationAction;
use App\DTOs\Application\CreateApplicationDTO;
use App\Enums\Source;
use App\Exceptions\LeadAlreadyConvertedException;
use App\Exceptions\ProgramNotAvailableInBranchException;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Scopes\BranchScope;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Staff manual entry: creates the lead and the application in one transaction, so an
 * application never exists without a lead and a failed application creation never leaves an
 * orphan lead behind. Every formal application originates from a lead — this is the only
 * route into `applications` that does not first go through the public lead lifecycle.
 */
final class CreateLeadWithApplicationAction
{
    public function __construct(
        private CreateLeadAction $createLead,
        private CreateApplicationAction $createApplication,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Raw application form data: branch_id, program_id,
     *                                      and the student/father/mother/relative fields.
     *                                      season_id and source are resolved here, not read
     *                                      from $data, so the caller cannot violate those
     *                                      domain invariants.
     */
    public function execute(array $data, User $actingUser): Application
    {
        $branch = Branch::findOrFail($data['branch_id']);

        // Authorized before any lead lookup, merge, or write: a tampered branch_id in the
        // request must never reach the duplicate resolver or touch the database. Goes
        // through the existing Application policy/Shield permission so this stays the single
        // source of truth for "who may create an application, and where."
        Gate::forUser($actingUser)->authorize('create', [Application::class, $branch]);

        return DB::transaction(function () use ($data, $branch) {
            $program = Program::findOrFail($data['program_id']);

            if (! $program->isAvailableIn($branch)) {
                throw new ProgramNotAvailableInBranchException($program, $branch);
            }

            // Resolved exactly once and handed to CreateLeadAction explicitly, so the lead
            // and the application can never end up with two independently resolved seasons.
            $season = Season::current($program->type);
            $source = Source::DASHBOARD;

            // Reuses Application's own guardian accessors (father when father_is_guardian,
            // otherwise mother, otherwise the relative) against a transient, unsaved instance
            // so the lead's guardian is derived identically to how the application itself
            // resolves its acting guardian — one rule, not a second copy of it.
            $guardian = new Application($data);

            $lead = $this->createLead->execute(
                whatsapp: (string) $guardian->guardian_phone,
                guardian_name: (string) $guardian->guardian_name,
                student_name: $data['student_name'],
                program_id: $program->id,
                branch_id: $branch->id,
                source: $source->value,
                data: ['mother_phone' => $data['mother_phone'] ?? null],
                season: $season,
            );

            if ($this->isAlreadyConverted($lead)) {
                throw new LeadAlreadyConvertedException($lead);
            }

            // A deduplicated lead keeps its own original season/branch/program/source/
            // affiliate (CreateLeadAction never overwrites those on a merge); the application
            // must agree with the *lead's actual* values, not blindly with the raw request.
            $dto = CreateApplicationDTO::fromFormData(
                [
                    'branch_id' => $lead->branch_id,
                    'program_id' => $lead->program_id,
                    'season_id' => $lead->season_id,
                    'source' => $lead->source,
                    'affiliate_id' => $lead->affiliate_id,
                ] + $data,
                $lead->id,
            );

            return $this->createApplication->execute($dto);
        });
    }

    /**
     * `applications.lead_id` is unique globally, not per-branch, so this check must never be
     * hidden by the branch-scoped presentation scope that `Application`/`Lead` carry for
     * listing purposes — a branch-scoped acting user must still detect a conversion that
     * happened (or is visible) outside their own branch.
     */
    private function isAlreadyConverted(Lead $lead): bool
    {
        return Application::withoutGlobalScope(BranchScope::class)
            ->where('lead_id', $lead->id)
            ->exists();
    }
}
