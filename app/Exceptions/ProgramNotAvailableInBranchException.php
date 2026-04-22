<?php

namespace App\Exceptions;

use App\Models\Branch;
use App\Models\Program;
use Exception;

class ProgramNotAvailableInBranchException extends Exception
{
    public function __construct(Program $program, Branch $branch)
    {
        parent::__construct(
            __('exceptions.program_not_available_in_branch', [
                'program' => $program->name,
                'branch' => $branch->name,
            ])
        );
    }
}
