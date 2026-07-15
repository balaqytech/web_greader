<?php

namespace App\Exceptions;

use App\Models\Program;
use App\Models\Season;
use InvalidArgumentException;

/**
 * Thrown by CreateLeadAction when a caller supplies an explicit Season that does not belong
 * to the target program's type, or is not currently active. An explicitly supplied season is
 * still an external input (e.g. CreateLeadWithApplicationAction resolves it once and hands it
 * in) and must be revalidated here rather than trusted, or a lead could end up filed under a
 * season for the wrong program type, or a closed/inactive one.
 */
class InvalidSeasonForProgramException extends InvalidArgumentException
{
    public function __construct(Season $season, Program $program)
    {
        parent::__construct(
            __('exceptions.invalid_season_for_program', [
                'season' => $season->name,
                'type' => $program->type->value,
            ])
        );
    }
}
