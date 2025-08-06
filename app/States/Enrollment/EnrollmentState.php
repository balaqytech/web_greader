<?php

namespace App\States\Enrollment;

use App\Models\ProgramEnrollment;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;
use Filament\Support\Contracts\HasLabel;

abstract class EnrollmentState extends State implements HasLabel
{
    abstract public function getLabel(): string;

    abstract public function color(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Pending::class, Transitions\DraftToPending::class)
            ->allowTransition(Draft::class, Canceled::class)
            ->allowTransition(Pending::class, Signed::class, Transitions\PendingToSigned::class)
            ->allowTransition(Pending::class, Rejected::class)
            ->allowTransition(Signed::class, Approved::class)
            ->allowTransition(Signed::class, Rejected::class)
            ->allowTransition(Approved::class, Completed::class)
            ->allowTransition(Approved::class, Rejected::class);
    }
} 