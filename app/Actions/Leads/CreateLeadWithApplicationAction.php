<?php

namespace App\Actions\Leads;

use App\Actions\Applications\CreateApplicationAction;
use App\DTOs\Application\CreateApplicationDTO;
use App\Enums\Source;
use App\Exceptions\LeadAlreadyConvertedException;
use App\Exceptions\ProgramNotAvailableInBranchException;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Program;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

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
    public function execute(array $data): Application
    {
        return DB::transaction(function () use ($data) {
            $branch = Branch::findOrFail($data['branch_id']);
            $program = Program::findOrFail($data['program_id']);

            if (! $program->isAvailableIn($branch)) {
                throw new ProgramNotAvailableInBranchException($program, $branch);
            }

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
            );

            if ($lead->application()->exists()) {
                throw new LeadAlreadyConvertedException($lead);
            }

            $dto = CreateApplicationDTO::fromFormData(
                [
                    'branch_id' => $branch->id,
                    'program_id' => $program->id,
                    'season_id' => $season->id,
                    'source' => $source,
                ] + $data,
                $lead->id,
            );

            return $this->createApplication->execute($dto);
        });
    }
}
